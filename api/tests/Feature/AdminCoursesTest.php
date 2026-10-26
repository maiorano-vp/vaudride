<?php

declare(strict_types=1);

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\PoisRelationManager;
use App\Models\Course;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('lists courses', function (): void {
    $courses = Course::factory()->count(2)->create();

    Livewire::test(ListCourses::class)->assertCanSeeTableRecords($courses);
});

it('creates a course', function (): void {
    Livewire::test(CreateCourse::class)
        ->fillForm([
            'slug' => 'tour-du-lac',
            'name' => ['fr' => 'Tour du lac', 'de' => '', 'en' => ''],
            'attribution' => '© swisstopo',
            'published' => true,
            'profile_file' => UploadedFile::fake()->createWithContent('profile.json', json_encode([
                'slug' => 'tour-du-lac',
                'distance_m' => 30_000,
                'elevation_gain_m' => 200,
                'points' => [
                    ['d' => 0, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 400, 'grade' => 0],
                    ['d' => 5, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 400, 'grade' => 0],
                ],
            ], JSON_THROW_ON_ERROR)),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Course::query()->where('slug', 'tour-du-lac')->firstOrFail()->published)->toBeTrue();
});

it('edits a course but never its slug', function (): void {
    $course = Course::factory()->create(['published' => false]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['published' => true, 'slug' => 'renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    $course->refresh();
    expect($course->published)->toBeTrue()->and($course->slug)->not->toBe('renamed');
});

it('manages points of interest from the course page', function (): void {
    $course = Course::factory()->create();

    Livewire::test(PoisRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
        ->callTableAction('create', data: [
            'title' => ['fr' => 'Château de Chillon'],
            'distance_m' => 1200,
            'lat' => 46.414,
            'lon' => 6.927,
        ])
        ->assertHasNoTableActionErrors();

    expect($course->pois()->count())->toBe(1);
});
