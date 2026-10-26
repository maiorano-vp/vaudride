<?php

declare(strict_types=1);

use App\Jobs\UploadRideToStrava;
use App\Models\Course;
use App\Models\Ride;
use App\Models\StravaConnection;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'services.strava.client_id' => 'client-1',
        'services.strava.client_secret' => 'secret-1',
        'services.frontend.url' => 'https://app.test',
    ]);
});

function stravaState(User $user, int $expiresIn = 600): string
{
    return Crypt::encryptString(json_encode(['user' => $user->id, 'exp' => now()->getTimestamp() + $expiresIn], JSON_THROW_ON_ERROR));
}

it('requires authentication for Strava actions', function (): void {
    $this->getJson('/api/v1/strava')->assertUnauthorized();
    $this->getJson('/api/v1/strava/connect')->assertUnauthorized();
    $this->deleteJson('/api/v1/strava')->assertUnauthorized();
});

it('returns the authorize URL with a signed state', function (): void {
    $user = User::factory()->create();

    $url = $this->actingAs($user, 'sanctum')->getJson('/api/v1/strava/connect')->assertOk()->json('url');

    expect($url)->toStartWith('https://www.strava.com/oauth/authorize?')
        ->toContain('client_id=client-1')
        ->toContain('scope=read%2Cactivity%3Awrite')
        ->toContain('state=');
});

it('answers 503 when Strava is not configured', function (): void {
    config(['services.strava.client_id' => '']);

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/strava/connect')->assertStatus(503);
});

it('stores the connection on a valid callback and redirects to the app', function (): void {
    $user = User::factory()->create();
    Http::fake(['www.strava.com/oauth/token' => Http::response([
        'access_token' => 'new-access',
        'refresh_token' => 'new-refresh',
        'expires_at' => now()->addHours(6)->getTimestamp(),
        'athlete' => ['id' => 4242],
    ])]);

    $this->get('/api/v1/strava/callback?'.http_build_query(['code' => 'abc', 'scope' => 'read,activity:write', 'state' => stravaState($user)]))
        ->assertRedirect('https://app.test/?strava=connected');

    $connection = StravaConnection::query()->where('user_id', $user->id)->firstOrFail();
    expect($connection->athlete_id)->toBe(4242)
        ->and($connection->access_token)->toBe('new-access')
        ->and($connection->scope)->toBe('read,activity:write');
    $this->assertDatabaseMissing('strava_connections', ['access_token' => 'new-access']); // stored encrypted
});

it('rejects a bad, expired or denied callback', function (): void {
    $user = User::factory()->create();

    $this->get('/api/v1/strava/callback?code=abc&state=garbage')->assertRedirect('https://app.test/?strava=error');
    $this->get('/api/v1/strava/callback?'.http_build_query(['code' => 'abc', 'state' => stravaState($user, -1)]))
        ->assertRedirect('https://app.test/?strava=error');
    $this->get('/api/v1/strava/callback?'.http_build_query(['error' => 'access_denied', 'state' => stravaState($user)]))
        ->assertRedirect('https://app.test/?strava=error');

    expect(StravaConnection::query()->count())->toBe(0);
});

it('reports and removes the connection', function (): void {
    $connection = StravaConnection::factory()->create(['athlete_id' => 7]);
    Http::fake(['www.strava.com/oauth/deauthorize' => Http::response([])]);

    $this->actingAs($connection->user, 'sanctum')->getJson('/api/v1/strava')
        ->assertOk()->assertJson(['connected' => true, 'athlete_id' => 7]);

    $this->actingAs($connection->user, 'sanctum')->deleteJson('/api/v1/strava')->assertNoContent();

    expect(StravaConnection::query()->count())->toBe(0);
    $this->actingAs($connection->user->fresh(), 'sanctum')->getJson('/api/v1/strava')->assertJson(['connected' => false]);
});

it('queues an upload for my ride', function (): void {
    Queue::fake();
    $connection = StravaConnection::factory()->create();
    $ride = Ride::factory()->for($connection->user)->create();

    $this->actingAs($connection->user, 'sanctum')->postJson('/api/v1/rides/'.$ride->id.'/strava')
        ->assertAccepted()->assertJson(['status' => 'queued']);

    Queue::assertPushed(UploadRideToStrava::class, fn (UploadRideToStrava $job): bool => $job->ride->is($ride));
});

it('refuses uploads without a connection, twice, or for others rides', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $ride = Ride::factory()->for($user)->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/rides/'.$ride->id.'/strava')->assertUnprocessable();

    $connection = StravaConnection::factory()->for($user)->create();
    $done = Ride::factory()->for($user)->create(['strava_activity_id' => 99]);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/rides/'.$done->id.'/strava')->assertConflict();

    $other = Ride::factory()->create();
    $this->actingAs($connection->user, 'sanctum')->postJson('/api/v1/rides/'.$other->id.'/strava')->assertNotFound();

    Queue::assertNothingPushed();
});

it('uploads the FIT file and records the Strava activity', function (): void {
    $connection = StravaConnection::factory()->create();
    $course = Course::factory()->create(['name' => ['fr' => 'Col du Marchairuz']]);
    $ride = Ride::factory()->for($connection->user)->for($course)->create();
    Http::fake([
        'www.strava.com/api/v3/uploads' => Http::response(['id' => 99, 'status' => 'Your activity is still being processed.']),
        'www.strava.com/api/v3/uploads/99' => Http::response(['id' => 99, 'activity_id' => 555, 'error' => null]),
    ]);

    UploadRideToStrava::dispatchSync($ride);

    expect($ride->fresh()->strava_activity_id)->toBe(555)
        ->and($ride->fresh()->strava_upload_id)->toBe(99);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://www.strava.com/api/v3/uploads'
        && $request->hasHeader('Authorization', 'Bearer '.$connection->access_token)
        && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'sport_type' && $part['contents'] === 'VirtualRide'));
});

it('refreshes an expired token before uploading', function (): void {
    $connection = StravaConnection::factory()->expired()->create();
    $ride = Ride::factory()->for($connection->user)->create();
    Http::fake([
        'www.strava.com/oauth/token' => Http::response([
            'access_token' => 'fresh', 'refresh_token' => 'fresh-refresh', 'expires_at' => now()->addHours(6)->getTimestamp(),
        ]),
        'www.strava.com/api/v3/uploads' => Http::response(['id' => 1]),
        'www.strava.com/api/v3/uploads/1' => Http::response(['id' => 1, 'activity_id' => 2, 'error' => null]),
    ]);

    UploadRideToStrava::dispatchSync($ride);

    expect($connection->fresh()->access_token)->toBe('fresh');
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fresh'));
});

it('records Strava processing errors on the ride', function (): void {
    $connection = StravaConnection::factory()->create();
    $ride = Ride::factory()->for($connection->user)->create();
    Http::fake([
        'www.strava.com/api/v3/uploads' => Http::response(['id' => 5]),
        'www.strava.com/api/v3/uploads/5' => Http::response(['id' => 5, 'activity_id' => null, 'error' => 'ride.fit duplicate of activity 1']),
    ]);

    UploadRideToStrava::dispatchSync($ride);

    expect($ride->fresh()->strava_error)->toBe('ride.fit duplicate of activity 1')
        ->and($ride->fresh()->strava_activity_id)->toBeNull();
});

it('records a client error from Strava without retrying', function (): void {
    $connection = StravaConnection::factory()->create();
    $ride = Ride::factory()->for($connection->user)->create();
    Http::fake(['www.strava.com/api/v3/uploads' => Http::response(['message' => 'Authorization Error'], 401)]);

    UploadRideToStrava::dispatchSync($ride);

    expect($ride->fresh()->strava_error)->toBe('Strava answered HTTP 401.');
});

it('queues an upload when a ride is posted with upload_to_strava and Strava is connected', function (): void {
    Queue::fake();
    $connection = StravaConnection::factory()->create();
    Course::factory()->create(['slug' => 'marchairuz']);

    $this->actingAs($connection->user, 'sanctum')->postJson('/api/v1/rides', [...ridePayload(), 'upload_to_strava' => true])->assertCreated();

    Queue::assertPushed(UploadRideToStrava::class);
});

it('does not upload when upload_to_strava is not requested', function (): void {
    Queue::fake();
    $connection = StravaConnection::factory()->create();
    Course::factory()->create(['slug' => 'marchairuz']);

    $this->actingAs($connection->user, 'sanctum')->postJson('/api/v1/rides', ridePayload())->assertCreated();

    Queue::assertNothingPushed();
});
