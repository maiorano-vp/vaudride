<?php

declare(strict_types=1);

use App\Filament\Widgets\ActivityStats;
use App\Filament\Widgets\CourseActivityTable;
use App\Filament\Widgets\PopularSegmentsTable;
use App\Filament\Widgets\RidesPerWeekChart;
use App\Models\Course;
use App\Models\Ride;
use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('shows the totals without naming anybody', function (): void {
    $player = User::factory()->create(['nickname' => 'Camille Rider', 'email' => 'camille@example.org']);
    Ride::factory()->for($player)->create(['distance_m' => 12_000, 'elevation_gain_m' => 300, 'started_at' => now()->subDay()]);
    Ride::factory()->for($player)->create(['distance_m' => 8_000, 'elevation_gain_m' => 200, 'started_at' => now()->subDays(60)]);

    Livewire::test(ActivityStats::class)
        ->assertSee('Joueurs')
        ->assertSee('20') // km
        ->assertSee('500 m') // climb
        ->assertSee('1 sur 30 jours')
        ->assertDontSee('Camille Rider')
        ->assertDontSee('camille@example.org');
});

it('does not count the admins as players', function (): void {
    User::factory()->count(2)->create();

    $stats = Livewire::test(ActivityStats::class)->instance();
    $values = collect((fn () => $this->getStats())->call($stats))->map(fn ($stat) => $stat->getValue());

    expect($values[0])->toBe('2');
});

it('charts the rides of the last twelve weeks', function (): void {
    Ride::factory()->count(2)->create(['started_at' => now()]);
    Ride::factory()->create(['started_at' => now()->subWeeks(3)]);
    Ride::factory()->create(['started_at' => now()->subWeeks(30)]); // out of the window

    $chart = Livewire::test(RidesPerWeekChart::class)->instance();
    $data = (fn () => $this->getData())->call($chart);

    expect($data['labels'])->toHaveCount(12)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(3)
        ->and(end($data['datasets'][0]['data']))->toBeGreaterThanOrEqual(1);
});

it('ranks the courses and segments by activity', function (): void {
    $busy = Course::factory()->create(['name' => ['fr' => 'Très roulé']]);
    $quiet = Course::factory()->create(['name' => ['fr' => 'Tranquille']]);
    Ride::factory()->count(3)->for($busy)->create();
    Ride::factory()->for($quiet)->create();
    $segment = Segment::factory()->for($busy)->create(['published' => true]);
    SegmentEffort::factory()->count(2)->create(['segment_id' => $segment->id]);

    Livewire::test(CourseActivityTable::class)->assertCanSeeTableRecords([$busy, $quiet], inOrder: true);
    Livewire::test(PopularSegmentsTable::class)->assertCanSeeTableRecords([$segment])->assertSee('2');
});
