<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Ride;
use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;

/**
 * A ride at a steady speed: one sample per second, `$speedMs` metres each, for `$seconds` seconds.
 *
 * @return list<array{t: int, d: float, p: int, c: int, h: int, s: float}>
 */
function steadySamples(int $seconds, float $speedMs): array
{
    return array_map(
        fn (int $t): array => ['t' => $t, 'd' => $t * $speedMs, 'p' => 200, 'c' => 85, 'h' => 140, 's' => $speedMs * 3.6],
        range(0, $seconds),
    );
}

it('requires authentication', function (): void {
    Course::factory()->create(['slug' => 'marchairuz']);

    $this->getJson('/api/v1/courses/marchairuz/ghosts')->assertUnauthorized();
    $this->getJson('/api/v1/segments/'.Segment::factory()->create()->id.'/ghost')->assertUnauthorized();
});

it('gives the fastest and the latest finished ride of the player, with time and distance only', function (): void {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'marchairuz', 'distance_m' => 100]);
    $fast = Ride::factory()->for($user)->for($course)->create(['started_at' => now()->subDays(3), 'duration_s' => 20, 'distance_m' => 100, 'samples' => steadySamples(20, 5)]);
    $latest = Ride::factory()->for($user)->for($course)->create(['started_at' => now()->subDay(), 'duration_s' => 50, 'distance_m' => 100, 'samples' => steadySamples(50, 2)]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/courses/marchairuz/ghosts')->assertOk();

    $response->assertJsonPath('data.best.duration_s', $fast->duration_s)
        ->assertJsonPath('data.last.duration_s', $latest->duration_s)
        ->assertJsonCount(21, 'data.best.samples')
        ->assertJsonPath('data.best.samples.2', [2, 10]); // [seconds, metres]: no power, cadence or heart rate
});

it('ignores unfinished rides and the rides of other players or courses', function (): void {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'marchairuz', 'distance_m' => 1000]);
    Ride::factory()->for($user)->for($course)->create(['distance_m' => 400]); // gave up halfway
    Ride::factory()->for($course)->create(['distance_m' => 1000]); // someone else's
    Ride::factory()->for($user)->create(['distance_m' => 1000]); // another course

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/courses/marchairuz/ghosts')
        ->assertOk()
        ->assertJsonPath('data.best', null)
        ->assertJsonPath('data.last', null);
});

it('counts a ride that stops a few metres short of the line as finished', function (): void {
    $user = User::factory()->create();
    $course = Course::factory()->create(['slug' => 'marchairuz', 'distance_m' => 1000]);
    Ride::factory()->for($user)->for($course)->create(['distance_m' => 985]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/courses/marchairuz/ghosts')
        ->assertOk()
        ->assertJsonPath('data.best.duration_s', 3);
});

it('answers 404 for an unknown course', function (): void {
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/courses/nope/ghosts')->assertNotFound();
});

it('gives the segment record as time since the start line, cut to the segment', function (): void {
    $viewer = User::factory()->create();
    $holder = User::factory()->create(['nickname' => 'Kom']);
    $course = Course::factory()->create(['distance_m' => 1000]);
    $segment = Segment::factory()->for($course)->create(['start_m' => 100, 'end_m' => 300, 'published' => true]);
    // 10 m/s: crosses 100 m at t = 10 s and 300 m at t = 30 s
    $ride = Ride::factory()->for($holder)->for($course)->create(['samples' => steadySamples(40, 10)]);
    SegmentEffort::factory()->for($segment)->for($ride)->for($holder)->create(['time_s' => 20.0]);
    // a slower effort by someone else must not be picked
    SegmentEffort::factory()->for($segment)->create(['time_s' => 25.0]);

    $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/segments/'.$segment->id.'/ghost')->assertOk();

    $response->assertJsonPath('data.nickname', 'Kom')
        ->assertJsonPath('data.time_s', 20)
        ->assertJsonPath('data.samples.0', [0, 100])
        ->assertJsonPath('data.samples.1', [1, 110])
        ->assertJsonPath('data.samples.19', [19, 290])
        ->assertJsonPath('data.samples.20', [20, 300]);
    expect($response->json('data.samples'))->toHaveCount(21);
});

it('has no ghost for a segment nobody rode, or one that is not published', function (): void {
    $user = User::factory()->create();
    $empty = Segment::factory()->create(['published' => true]);
    $draft = Segment::factory()->create(['published' => false]);
    SegmentEffort::factory()->for($draft)->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/segments/'.$empty->id.'/ghost')->assertNotFound();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/segments/'.$draft->id.'/ghost')->assertNotFound();
});
