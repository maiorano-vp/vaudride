<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Ride;
use App\Models\User;

function ridePayload(string $course = 'marchairuz'): array
{
    return [
        'course' => $course,
        'started_at' => now()->subHour()->toIso8601String(),
        'elevation_gain_m' => 12,
        'samples' => [
            ['t' => 0, 'd' => 0, 'p' => 100, 'c' => 80, 'h' => 120, 's' => 15.5],
            ['t' => 1, 'd' => 5, 'p' => 200, 'c' => 85, 'h' => null, 's' => 18.0],
            ['t' => 2, 'd' => 99999, 'p' => 300, 's' => 20.0],
        ],
    ];
}

it('requires authentication', function (): void {
    $this->getJson('/api/v1/rides')->assertUnauthorized();
    $this->postJson('/api/v1/rides', [])->assertUnauthorized();
});

it('records a ride and derives the summary from the samples', function (): void {
    $user = User::factory()->create();
    Course::factory()->create(['slug' => 'marchairuz', 'distance_m' => 10_000]);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/rides', ridePayload())
        ->assertCreated()
        ->assertJsonPath('data.duration_s', 2)
        ->assertJsonPath('data.distance_m', 10_000) // clamped to the course length
        ->assertJsonPath('data.avg_power_w', 200)
        ->assertJsonPath('data.max_power_w', 300)
        ->assertJsonPath('data.avg_heart_rate', 120);

    expect($user->rides()->count())->toBe(1);
});

it('rejects invalid ride data', function (): void {
    $user = User::factory()->create();
    Course::factory()->unpublished()->create(['slug' => 'hidden']);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/rides', ridePayload('hidden'))
        ->assertUnprocessable()->assertJsonValidationErrors(['course']);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/rides', ['samples' => [['t' => 0]]])
        ->assertUnprocessable()->assertJsonValidationErrors(['course', 'started_at', 'samples']);
});

it('lists only my rides without samples, and shows one with samples', function (): void {
    $me = User::factory()->create();
    $mine = Ride::factory()->for($me)->create();
    Ride::factory()->create();

    $this->actingAs($me, 'sanctum')->getJson('/api/v1/rides')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.samples');

    $this->actingAs($me, 'sanctum')->getJson('/api/v1/rides/'.$mine->id)
        ->assertOk()
        ->assertJsonCount(3, 'data.samples');
});

it('hides other users rides', function (): void {
    $other = Ride::factory()->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/rides/'.$other->id)->assertNotFound();
});
