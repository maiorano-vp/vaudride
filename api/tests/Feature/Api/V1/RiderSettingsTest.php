<?php

declare(strict_types=1);

use App\Models\Ride;
use App\Models\User;

/** A player as the API loads them: with every column, the new ones included. */
function player(): User
{
    return User::factory()->create()->fresh();
}

function validSettings(array $override = []): array
{
    return [
        'weightKg' => 68.5, 'bikeKg' => 9, 'position' => 'aero', 'cda' => 0.28, 'ftp' => 230, 'difficultyPct' => 60,
        ...$override,
    ];
}

/**
 * A ride at a steady 100 W with `$watts` for `$effortS` seconds from second `$from`.
 *
 * @return list<array{t: int, d: int, p: int, s: float}>
 */
function rideWithEffort(int $totalS, int $from, int $effortS, int $watts, int $every = 1): array
{
    $samples = [];

    for ($t = 0; $t <= $totalS; $t += $every) {
        $samples[] = ['t' => $t, 'd' => $t * 8, 'p' => $t >= $from && $t < $from + $effortS ? $watts : 100, 's' => 28.8];
    }

    return $samples;
}

it('has no settings until the player saves some', function (): void {
    $this->actingAs(player(), 'sanctum')->getJson('/api/v1/me/settings')
        ->assertOk()->assertJsonPath('data', null)->assertJsonPath('updated_at', null);
});

it('saves the settings and gives them back with the time of the change', function (): void {
    $user = player();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/settings', validSettings())
        ->assertOk()
        ->assertJsonPath('data.weightKg', 68.5)
        ->assertJsonPath('data.difficultyPct', 60)
        ->assertJsonPath('data.position', 'aero');

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/settings')
        ->assertJsonPath('data.ftp', 230)
        ->assertJsonStructure(['updated_at']);
    expect($user->fresh()->rider_settings_updated_at)->not->toBeNull();
});

it('keeps each player’s settings to themselves and out of the profile', function (): void {
    $user = player();
    $other = player();
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/settings', validSettings());

    $this->actingAs($other, 'sanctum')->getJson('/api/v1/me/settings')->assertJsonPath('data', null);
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')->assertJsonMissingPath('data.rider_settings')->assertJsonMissing(['weightKg' => 68.5]);
});

it('erases the settings, weight included, from the server on request', function (): void {
    $user = player();
    $this->actingAs($user, 'sanctum')->putJson('/api/v1/me/settings', validSettings())->assertOk();

    $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me/settings')->assertNoContent();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/settings')->assertJsonPath('data', null)->assertJsonPath('updated_at', null);
    $stored = $user->fresh();
    expect($stored->rider_settings)->toBeNull()->and($stored->rider_settings_updated_at)->toBeNull();
});

it('only erases the settings of the player who asks', function (): void {
    $user = player();
    $other = player();
    $this->actingAs($other, 'sanctum')->putJson('/api/v1/me/settings', validSettings(['ftp' => 300]));

    $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me/settings')->assertNoContent();

    expect($other->fresh()->rider_settings['ftp'])->toBe(300);
});

it('rejects values out of range or unknown', function (array $override, string $field): void {
    $this->actingAs(player(), 'sanctum')
        ->putJson('/api/v1/me/settings', validSettings($override))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'too light' => [['weightKg' => 10], 'weightKg'],
    'too heavy' => [['weightKg' => 500], 'weightKg'],
    'text weight' => [['weightKg' => 'heavy'], 'weightKg'],
    'bike' => [['bikeKg' => 60], 'bikeKg'],
    'position' => [['position' => 'standing'], 'position'],
    'drag area' => [['cda' => 2], 'cda'],
    'ftp' => [['ftp' => 1000], 'ftp'],
    'difficulty too low' => [['difficultyPct' => 10], 'difficultyPct'],
    'difficulty not a 5 % step' => [['difficultyPct' => 62], 'difficultyPct'],
    'missing' => [['ftp' => null], 'ftp'],
]);

it('needs a signed-in player', function (): void {
    $this->getJson('/api/v1/me/settings')->assertUnauthorized();
    $this->putJson('/api/v1/me/settings', validSettings())->assertUnauthorized();
    $this->getJson('/api/v1/me/ftp-estimate')->assertUnauthorized();
    $this->deleteJson('/api/v1/me/settings')->assertUnauthorized();
});

it('suggests no FTP before a 20-minute effort', function (): void {
    $user = player();
    Ride::factory()->for($user)->create(['duration_s' => 600, 'samples' => rideWithEffort(600, 0, 600, 300)]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/ftp-estimate')->assertOk()->assertJsonPath('data', null);
});

it('suggests 95 % of the best 20 minutes, from the ride that holds it', function (): void {
    $user = player();
    Ride::factory()->for($user)->create(['duration_s' => 1800, 'samples' => rideWithEffort(1800, 0, 1800, 150)]);
    $best = Ride::factory()->for($user)->create(['duration_s' => 2400, 'samples' => rideWithEffort(2400, 400, 1200, 300)]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/ftp-estimate')
        ->assertOk()
        ->assertJsonPath('data.avg_power_w', 300)
        ->assertJsonPath('data.ftp', 285)
        ->assertJsonPath('data.ride_id', $best->id);
});

it('finds a 20-minute effort wherever it starts, not only on the minute', function (): void {
    $user = player();
    Ride::factory()->for($user)->create(['duration_s' => 3000, 'samples' => rideWithEffort(3000, 777, 1200, 260)]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/ftp-estimate')->assertJsonPath('data.avg_power_w', 260)->assertJsonPath('data.ftp', 247);
});

it('ignores rides with too few samples, other players and old rides', function (): void {
    $user = player();
    Ride::factory()->for($user)->create(['duration_s' => 1500, 'samples' => rideWithEffort(1500, 0, 1500, 400, every: 3)]); // a sample every 3 s
    Ride::factory()->for(User::factory())->create(['duration_s' => 1500, 'samples' => rideWithEffort(1500, 0, 1500, 400)]);
    Ride::factory()->for($user)->create(['duration_s' => 1500, 'started_at' => now()->subDays(400), 'samples' => rideWithEffort(1500, 0, 1500, 400)]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/ftp-estimate')->assertJsonPath('data', null);
});
