<?php

declare(strict_types=1);

use App\Actions\DetectClimbs;
use App\Actions\StoreCourseProfile;
use App\Enums\ClimbCategory;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\SegmentsRelationManager;
use App\Models\Course;
use App\Models\Segment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A profile sampled every 5 m from (distance, elevation) legs: [[length_m, elevation_change_m], ...].
 *
 * @param  list<array{int, int}>  $legs
 * @return list<array{d: int, ele: float}>
 */
function profileFromLegs(array $legs, float $start = 400): array
{
    $points = [['d' => 0, 'ele' => $start]];
    $d = 0;
    $ele = $start;

    foreach ($legs as [$length, $change]) {
        for ($i = 0; $i < $length / 5; $i++) {
            $d += 5;
            $ele += $change / ($length / 5);
            $points[] = ['d' => $d, 'ele' => $ele];
        }
    }

    return $points;
}

it('finds a sustained climb and its category', function (): void {
    $climbs = (new DetectClimbs)->handle(profileFromLegs([[1000, 0], [2000, 100], [1000, 0]]));

    expect($climbs)->toHaveCount(1)
        ->and($climbs[0]['start_m'])->toBe(1000)
        ->and($climbs[0]['end_m'])->toBe(3000)
        ->and($climbs[0]['gain_m'])->toBe(100)
        ->and($climbs[0]['avg_grade'])->toBe(5.0)
        ->and($climbs[0]['category'])->toBe(ClimbCategory::Cat4); // 2000 m × 5 % = 10 000
});

it('merges climbs separated by a small dip and splits them on a deep one', function (): void {
    $small = (new DetectClimbs)->handle(profileFromLegs([[1500, 90], [200, -6], [1500, 90]]));
    $deep = (new DetectClimbs)->handle(profileFromLegs([[1500, 90], [400, -40], [1500, 90]]));

    expect($small)->toHaveCount(1)->and($deep)->toHaveCount(2);
});

it('ignores flats, descents and short or gentle rises', function (): void {
    expect((new DetectClimbs)->handle(profileFromLegs([[3000, 0], [2000, -100]])))->toBe([])
        ->and((new DetectClimbs)->handle(profileFromLegs([[300, 30]])))->toBe([]) // too short
        ->and((new DetectClimbs)->handle(profileFromLegs([[3000, 60]])))->toBe([]); // 2 %
});

it('grades climbs with the length × grade thresholds', function (): void {
    expect(ClimbCategory::fromScore(7_999))->toBeNull()
        ->and(ClimbCategory::fromScore(8_000))->toBe(ClimbCategory::Cat4)
        ->and(ClimbCategory::fromScore(32_000))->toBe(ClimbCategory::Cat2)
        ->and(ClimbCategory::fromScore(80_000))->toBe(ClimbCategory::Hc);
});

it('returns only the published segments with a course', function (): void {
    $course = Course::factory()->create(['slug' => 'a-course', 'published' => true]);
    Segment::factory()->for($course)->create(['name' => ['fr' => 'Visible'], 'start_m' => 100, 'end_m' => 900, 'category' => ClimbCategory::Cat4]);
    Segment::factory()->for($course)->create(['name' => ['fr' => 'Brouillon'], 'published' => false]);

    $this->getJson('/api/v1/courses/a-course')
        ->assertOk()
        ->assertJsonCount(1, 'data.segments')
        ->assertJsonPath('data.segments.0.name.fr', 'Visible')
        ->assertJsonPath('data.segments.0.category', 'cat4')
        ->assertJsonPath('data.segments.0.start_m', 100);
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Storage::fake(StoreCourseProfile::DISK);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('proposes the detected climbs as unpublished segments, once', function (): void {
        $course = Course::factory()->create(['slug' => 'a-course', 'distance_m' => 4000]);
        Storage::disk(StoreCourseProfile::DISK)->put(
            StoreCourseProfile::path('a-course'),
            json_encode(['points' => profileFromLegs([[1000, 0], [2000, 100], [1000, 0]])], JSON_THROW_ON_ERROR),
        );

        $page = Livewire::test(SegmentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class]);
        $page->callTableAction('detect');
        $page->callTableAction('detect');

        $segments = $course->segments()->get();
        expect($segments)->toHaveCount(1)
            ->and($segments[0]->published)->toBeFalse()
            ->and($segments[0]->category)->toBe(ClimbCategory::Cat4)
            ->and($segments[0]->name['fr'])->toBe('Montée 1');
    });

    it('warns instead of failing when the course has no profile', function (): void {
        $course = Course::factory()->create();

        Livewire::test(SegmentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('detect')
            ->assertNotified('Ce parcours n’a pas encore de profil.');

        expect($course->segments()->count())->toBe(0);
    });

    it('creates a segment by hand and rejects one past the end of the course', function (): void {
        $course = Course::factory()->create(['distance_m' => 5000]);
        $page = Livewire::test(SegmentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class]);

        $page->callTableAction('create', data: ['name' => ['fr' => 'Ma montée'], 'start_m' => 1000, 'end_m' => 2500, 'category' => 'cat3', 'published' => true])
            ->assertHasNoTableActionErrors();
        $page->callTableAction('create', data: ['name' => ['fr' => 'Trop loin'], 'start_m' => 1000, 'end_m' => 9000])
            ->assertHasTableActionErrors(['end_m']);

        expect($course->segments()->count())->toBe(1);
    });
});
