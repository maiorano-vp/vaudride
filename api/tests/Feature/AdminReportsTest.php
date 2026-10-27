<?php

declare(strict_types=1);

use App\Actions\ActivityReportCsv;
use App\Actions\BuildActivityReport;
use App\Filament\Pages\Reports;
use App\Filament\Reports\Widgets\CoursesReportTable;
use App\Filament\Reports\Widgets\ReportStats;
use App\Models\Course;
use App\Models\Ride;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

/** Two courses, three players (one of them rides twice), one ride that stops halfway, and an admin whose rides do not count. */
function seedActivity(): array
{
    $long = Course::factory()->create(['slug' => 'long', 'name' => ['fr' => 'Parcours long'], 'distance_m' => 1000]);
    $short = Course::factory()->create(['slug' => 'short', 'name' => ['fr' => 'Parcours court'], 'distance_m' => 600]);
    $camille = User::factory()->create(['nickname' => 'Camille Rider', 'email' => 'camille@example.org']);
    $noah = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    Ride::factory()->for($camille)->for($long)->create(['distance_m' => 1000, 'elevation_gain_m' => 100, 'duration_s' => 600, 'started_at' => now()->subDays(2)]);
    Ride::factory()->for($camille)->for($long)->create(['distance_m' => 400, 'elevation_gain_m' => 40, 'duration_s' => 200, 'started_at' => now()->subDays(3)]); // gave up
    Ride::factory()->for($noah)->for($long)->create(['distance_m' => 985, 'elevation_gain_m' => 90, 'duration_s' => 400, 'started_at' => now()->subDays(5)]); // finished within 20 m
    Ride::factory()->for($camille)->for($short)->create(['distance_m' => 600, 'elevation_gain_m' => 50, 'duration_s' => 300, 'started_at' => now()->subDays(1)]);
    Ride::factory()->for($admin)->for($long)->create(['started_at' => now()->subDay()]);

    return [$camille, $noah];
}

it('totals the period without counting admins', function (): void {
    seedActivity();

    $report = app(BuildActivityReport::class)->forFilters(null);

    expect($report['totals'])->toMatchArray([
        'rides' => 4,
        'active_players' => 2,
        'returning_players' => 1,
        'elevation_gain_m' => 280,
        'duration_s' => 1500,
        'avg_duration_s' => 375,
    ])->and($report['totals']['km'])->toBe(3.0)
        ->and($report['totals']['finished_share'])->toBe(0.75); // 2.985 km, rounded to 100 m
});

it('ranks the courses with their players and the share of finished rides', function (): void {
    seedActivity();

    $courses = app(BuildActivityReport::class)->forFilters(null)['courses'];

    expect($courses)->toHaveCount(2)
        ->and($courses[0])->toMatchArray(['slug' => 'long', 'rides' => 3, 'players' => 2, 'finished_share' => 0.667])
        ->and($courses[1])->toMatchArray(['slug' => 'short', 'rides' => 1, 'players' => 1, 'finished_share' => 1.0]);
});

it('counts new players in the period, not the admins', function (): void {
    User::factory()->create(['created_at' => now()->subDays(10)]);
    User::factory()->create(['created_at' => now()->subDays(200)]);
    User::factory()->create(['is_admin' => true]);

    expect(app(BuildActivityReport::class)->forFilters(null)['totals']['new_players'])->toBe(1);
});

it('follows the chosen period', function (): void {
    Ride::factory()->create(['started_at' => now()->subDays(10)]);
    Ride::factory()->create(['started_at' => now()->subDays(100)]);
    $report = app(BuildActivityReport::class);
    $rides = fn (array $filters): int => $report->forFilters($filters)['totals']['rides'];

    expect($rides(['preset' => '30d']))->toBe(1)
        ->and($rides(['preset' => '90d']))->toBe(1)
        ->and($rides(['preset' => '12m']))->toBe(2)
        ->and($rides(['preset' => 'all']))->toBe(2)
        ->and($rides(['preset' => 'custom', 'from' => now()->subDays(120)->toDateString(), 'to' => now()->subDays(60)->toDateString()]))->toBe(1)
        ->and($rides(['preset' => 'custom', 'from' => now()->toDateString(), 'to' => now()->subDays(60)->toDateString()]))->toBe(1) // reversed dates: back to 90 days
        ->and($rides(['preset' => 'nonsense']))->toBe(1);
});

it('charts every week of the period, empty ones too, and switches to months for a long period', function (): void {
    Ride::factory()->count(2)->create(['started_at' => now()]);
    $report = app(BuildActivityReport::class);

    $weeks = $report->forFilters(['preset' => '30d'])['timeline'];
    $months = $report->forFilters(['preset' => '12m'])['timeline'];

    expect($weeks['unit'])->toBe('week')
        ->and(count($weeks['rows']))->toBeBetween(5, 6)
        ->and(array_sum(array_column($weeks['rows'], 'rides')))->toBe(2)
        ->and($months['unit'])->toBe('month')
        ->and(count($months['rows']))->toBeBetween(12, 13)
        ->and(array_sum(array_column($months['rows'], 'rides')))->toBe(2);
});

it('splits the rides by weekday and month in Swiss time', function (): void {
    // Sunday 23:30 UTC is already Monday in Zurich, in winter and in summer
    Ride::factory()->create(['started_at' => '2026-01-04 23:30:00']);
    Ride::factory()->create(['started_at' => '2026-07-05 23:30:00']);
    $report = app(BuildActivityReport::class)->handle(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

    expect($report['weekdays'][0])->toBe(['label' => 'Lundi', 'rides' => 2])
        ->and($report['weekdays'][6]['rides'])->toBe(0)
        ->and($report['months'][0]['rides'])->toBe(1)
        ->and($report['months'][6]['rides'])->toBe(1);
});

it('shows the reports page to admins only, without naming anybody', function (): void {
    seedActivity();

    $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(Reports::class)->assertOk()
        ->assertSee('Rapports')
        ->assertSee('Exporter les parcours (CSV)')
        ->assertDontSee('Camille Rider')
        ->assertDontSee('camille@example.org');
    Livewire::test(ReportStats::class, ['pageFilters' => ['preset' => '90d']])->assertSee('Joueurs actifs')->assertSee('75 %');
    Livewire::test(CoursesReportTable::class, ['pageFilters' => ['preset' => '90d']])->assertSee('Parcours long')->assertSee('Parcours court');
});

it('exports the tables as CSV for Excel', function (): void {
    seedActivity();
    $report = app(BuildActivityReport::class)->forFilters(null);
    $csv = app(ActivityReportCsv::class);

    $parse = fn (string $content): array => array_map(
        fn (string $line): array => str_getcsv($line, ';', '"', ''),
        explode("\n", mb_trim(mb_substr($content, mb_strlen("\u{FEFF}")))),
    );
    $courses = $csv->courses($report);
    $timeline = $csv->timeline($report);

    expect($courses)->toStartWith("\u{FEFF}") // Excel reads the accents as UTF-8
        ->and($parse($courses))->toBe([
            ['Parcours', 'Identifiant', 'Sorties', 'Joueurs', 'Kilomètres', 'Dénivelé (m)', 'Sorties terminées (%)'],
            ['Parcours long', 'long', '3', '2', '2,4', '230', '66,7'], // decimal comma
            ['Parcours court', 'short', '1', '1', '0,6', '50', '100'],
        ])
        ->and($parse($timeline)[0])->toBe(['Semaine du', 'Sorties', 'Joueurs actifs', 'Nouveaux joueurs']);
});

it('downloads the CSV from the page with the period in the file name', function (): void {
    seedActivity();
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $report = app(BuildActivityReport::class)->forFilters(null);
    $name = sprintf('vaud-ride-parcours-%s_%s.csv', $report['from']->format('Y-m-d'), $report['to']->format('Y-m-d'));

    Livewire::test(Reports::class)->callAction('exportCourses')->assertFileDownloaded($name);
});
