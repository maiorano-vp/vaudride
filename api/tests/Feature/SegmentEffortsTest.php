<?php

declare(strict_types=1);

use App\Filament\Resources\SegmentEfforts\Pages\ListSegmentEfforts;
use App\Models\Course;
use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Samples of a ride at a steady speed, one per second, covering `$untilM` metres.
 *
 * @return list<array{t: int, d: float, p: int, c: null, h: null, s: float}>
 */
function steadyRide(float $speedMs, float $untilM, float $startM = 0, int $power = 200): array
{
    $samples = [];

    for ($t = 0; $startM + $speedMs * $t <= $untilM; $t++) {
        $samples[] = ['t' => $t, 'd' => round($startM + $speedMs * $t, 1), 'p' => $power, 'c' => null, 'h' => null, 's' => $speedMs * 3.6];
    }

    return $samples;
}

function postRide(User $user, Course $course, array $samples)
{
    return test()->actingAs($user->fresh(), 'sanctum')->postJson('/api/v1/rides', [
        'course' => $course->slug,
        'started_at' => now()->subHour()->toIso8601String(),
        'elevation_gain_m' => 100,
        'samples' => $samples,
    ]);
}

beforeEach(function (): void {
    $this->course = Course::factory()->create(['published' => true, 'distance_m' => 5000]);
    $this->segment = Segment::factory()->for($this->course)->create(['start_m' => 1000, 'end_m' => 2000, 'published' => true]);
});

it('times a ride on a segment and ranks it', function (): void {
    $response = postRide(User::factory()->create(), $this->course, steadyRide(5, 3000))->assertCreated();

    $response->assertJsonCount(1, 'data.efforts')
        ->assertJsonPath('data.efforts.0.segment_id', $this->segment->id)
        ->assertJsonPath('data.efforts.0.time_s', 200)
        ->assertJsonPath('data.efforts.0.avg_power_w', 200)
        ->assertJsonPath('data.efforts.0.rank', 1)
        ->assertJsonPath('data.efforts.0.is_kom', true);
});

it('interpolates between samples when the line falls between two of them', function (): void {
    $segment = Segment::factory()->for($this->course)->create(['start_m' => 1002, 'end_m' => 2002, 'published' => true]);

    postRide(User::factory()->create(), $this->course, steadyRide(4, 3000))->assertCreated();

    expect((float) SegmentEffort::query()->where('segment_id', $segment->id)->firstOrFail()->time_s)->toBe(250.0);
});

it('keeps nothing when the ride does not cover the segment', function (array $samples): void {
    postRide(User::factory()->create(), $this->course, $samples)->assertCreated()->assertJsonCount(0, 'data.efforts');
})->with([
    'stops before the end' => fn () => steadyRide(5, 1500),
    'starts after the start line' => fn () => steadyRide(5, 3000, startM: 1200),
    'teleports through the segment' => fn () => [
        ['t' => 0, 'd' => 0, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
        ['t' => 100, 'd' => 900, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
        ['t' => 101, 'd' => 1500, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
        ['t' => 102, 'd' => 2100, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
    ],
    'no sample for half a minute around the start line' => fn () => [
        ['t' => 0, 'd' => 0, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
        ['t' => 40, 'd' => 800, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
        ['t' => 80, 'd' => 1600, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
        ['t' => 120, 'd' => 2400, 'p' => 200, 'c' => null, 'h' => null, 's' => 18],
    ],
]);

it('ignores segments that are not published', function (): void {
    $this->segment->update(['published' => false]);

    postRide(User::factory()->create(), $this->course, steadyRide(5, 3000))->assertJsonCount(0, 'data.efforts');
});

it('ranks riders by their best time, ties sharing a rank, and tells who is who', function (): void {
    [$ana, $ben, $cleo, $dan] = User::factory()->count(4)->sequence(['name' => 'Ana'], ['name' => 'Ben'], ['name' => 'Cleo'], ['name' => 'Dan'])->create()->all();
    foreach ([[$ana, 200], [$ana, 190], [$ben, 195], [$cleo, 195], [$dan, 210]] as [$user, $time]) {
        SegmentEffort::factory()->create(['segment_id' => $this->segment->id, 'user_id' => $user->id, 'time_s' => $time]);
    }

    $response = $this->actingAs($ben->fresh(), 'sanctum')->getJson("/api/v1/segments/{$this->segment->id}/leaderboard")->assertOk();

    expect($response->json('data.*.name'))->toBe(['Ana', 'Ben', 'Cleo', 'Dan'])
        ->and($response->json('data.*.rank'))->toBe([1, 2, 2, 4])
        ->and($response->json('data.*.time_s'))->toBe([190, 195, 195, 210])
        ->and($response->json('data.*.is_me'))->toBe([false, true, false, false])
        ->and($response->json('me'))->toBe(['rank' => 2, 'time_s' => 195])
        ->and($response->getContent())->not->toContain($ana->email);
});

it('gives a slower ride the rank it would have', function (): void {
    SegmentEffort::factory()->create(['segment_id' => $this->segment->id, 'time_s' => 150]);

    postRide(User::factory()->create(), $this->course, steadyRide(5, 3000))
        ->assertJsonPath('data.efforts.0.rank', 2)
        ->assertJsonPath('data.efforts.0.is_kom', false);
});

it('keeps the leaderboard for signed-in players and published segments', function (): void {
    $this->getJson("/api/v1/segments/{$this->segment->id}/leaderboard")->assertUnauthorized();

    $draft = Segment::factory()->for($this->course)->create(['published' => false]);
    $this->actingAs(User::factory()->create()->fresh(), 'sanctum')->getJson("/api/v1/segments/{$draft->id}/leaderboard")->assertNotFound();
});

it('shows the KOM time on the public course, without saying whose it is', function (): void {
    $queen = User::factory()->create(['name' => 'Reine des cols']);
    SegmentEffort::factory()->create(['segment_id' => $this->segment->id, 'user_id' => $queen->id, 'time_s' => 180.5]);
    SegmentEffort::factory()->create(['segment_id' => $this->segment->id, 'time_s' => 200]);

    $response = $this->getJson("/api/v1/courses/{$this->course->slug}")->assertOk()->assertJsonPath('data.segments.0.kom_time_s', 180.5);

    expect($response->getContent())->not->toContain('Reine des cols');
});

it('has no KOM time before anybody rode the segment', function (): void {
    $this->getJson("/api/v1/courses/{$this->course->slug}")->assertJsonPath('data.segments.0.kom_time_s', null);
});

it('lets an admin remove a suspicious time', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $effort = SegmentEffort::factory()->create(['segment_id' => $this->segment->id, 'time_s' => 10]);

    Livewire::test(ListSegmentEfforts::class)
        ->assertCanSeeTableRecords([$effort])
        ->callAction(TestAction::make('delete')->table($effort));

    expect(SegmentEffort::query()->count())->toBe(0);
});
