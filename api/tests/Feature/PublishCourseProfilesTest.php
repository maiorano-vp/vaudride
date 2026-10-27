<?php

declare(strict_types=1);

use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates unpublished courses from the built profiles, once', function (): void {
    Storage::fake('local');
    $dist = sys_get_temp_dir().'/vaudride-dist-'.uniqid();
    config(['courses.dist_path' => $dist]);
    Course::factory()->create(['slug' => 'deja-la']);

    foreach (['col-test', 'deja-la'] as $slug) {
        File::ensureDirectoryExists("{$dist}/{$slug}");
        File::put("{$dist}/{$slug}/profile.json", json_encode([
            'slug' => $slug, 'name' => 'Col de test', 'version' => 1, 'distance_m' => 1000.4, 'elevation_gain_m' => 80.2,
            'points' => [['d' => 0, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 500, 'grade' => 0], ['d' => 1000, 'lat' => 46.51, 'lon' => 6.6, 'ele' => 580, 'grade' => 8]],
        ]));
    }

    $this->artisan('course:publish')->assertSuccessful();
    $this->artisan('course:publish')->assertSuccessful();

    $course = Course::query()->where('slug', 'col-test')->sole();
    expect($course->name['fr'])->toBe('Col de test')
        ->and($course->distance_m)->toBe(1000)
        ->and($course->elevation_gain_m)->toBe(80)
        ->and($course->published)->toBeFalse()
        ->and(Course::query()->count())->toBe(2);
    Storage::disk('local')->assertExists('courses/col-test/profile.json');

    File::deleteDirectory($dist);
});

it('publishes and unpublishes courses from the admin list', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $courses = Course::factory()->count(2)->create(['published' => false]);

    Filament::setCurrentPanel('admin');
    $list = Livewire::actingAs($admin)->test(ListCourses::class);

    $list->callTableAction('togglePublished', $courses[0]);
    expect($courses[0]->fresh()->published)->toBeTrue();

    $list->callTableAction('togglePublished', $courses[0]);
    expect($courses[0]->fresh()->published)->toBeFalse();

    $list->callTableBulkAction('publish', $courses);
    expect(Course::query()->where('published', true)->count())->toBe(2);
});
