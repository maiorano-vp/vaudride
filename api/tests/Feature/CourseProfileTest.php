<?php

declare(strict_types=1);

use App\Actions\StoreCourseProfile;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Models\Course;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function profileJson(string $slug = 'a-course', array $override = []): string
{
    return json_encode([
        'slug' => $slug,
        'name' => 'A course',
        'version' => 1,
        'distance_m' => 5000.4,
        'elevation_gain_m' => 120.6,
        'points' => [
            ['d' => 0, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 400, 'grade' => 1.5],
            ['d' => 5, 'lat' => 46.5001, 'lon' => 6.6001, 'ele' => 400.1, 'grade' => 2],
        ],
        'pois' => [],
        'attribution' => '© swisstopo',
        ...$override,
    ], JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    Storage::fake(StoreCourseProfile::DISK);
});

it('stores a valid profile and returns the figures to keep on the course', function (): void {
    $summary = resolve(StoreCourseProfile::class)->handle('a-course', profileJson());

    expect($summary)->toBe(['distance_m' => 5000, 'elevation_gain_m' => 121]);
    Storage::disk(StoreCourseProfile::DISK)->assertExists('courses/a-course/profile.json');
});

it('rejects broken, mismatching or incomplete profiles without storing them', function (string $json): void {
    expect(fn () => resolve(StoreCourseProfile::class)->handle('a-course', $json))->toThrow(ValidationException::class);
    Storage::disk(StoreCourseProfile::DISK)->assertMissing('courses/a-course/profile.json');
})->with([
    'not json' => '{nope',
    'other slug' => fn () => profileJson('another-course'),
    'one point' => fn () => profileJson(override: ['points' => [['d' => 0, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 400, 'grade' => 1]]]),
    'point without grade' => fn () => profileJson(override: ['points' => [['d' => 0, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 400], ['d' => 5, 'lat' => 46.5, 'lon' => 6.6, 'ele' => 400]]]),
]);

it('serves the profile of a published course with a cache validator', function (): void {
    $course = Course::factory()->create(['slug' => 'a-course', 'published' => true]);
    resolve(StoreCourseProfile::class)->handle($course->slug, profileJson());

    $response = $this->get('/api/v1/courses/a-course/profile')->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->json('slug'))->toBe('a-course');

    $this->get('/api/v1/courses/a-course/profile', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
});

it('returns 404 for unpublished courses and missing files', function (): void {
    Course::factory()->create(['slug' => 'draft', 'published' => false]);
    Course::factory()->create(['slug' => 'no-file', 'published' => true]);
    resolve(StoreCourseProfile::class)->handle('draft', profileJson('draft'));

    $this->get('/api/v1/courses/draft/profile')->assertNotFound();
    $this->get('/api/v1/courses/no-file/profile')->assertNotFound();
    $this->get('/api/v1/courses/unknown/profile')->assertNotFound();
});

it('creates a course from an uploaded profile in the admin', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateCourse::class)
        ->fillForm([
            'slug' => 'a-course',
            'name' => ['fr' => 'Un parcours'],
            'attribution' => '© swisstopo',
            'profile_file' => UploadedFile::fake()->createWithContent('profile.json', profileJson()),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $course = Course::query()->where('slug', 'a-course')->firstOrFail();
    expect($course->distance_m)->toBe(5000)->and($course->elevation_gain_m)->toBe(121)->and($course->version)->toBe(1);
    Storage::disk(StoreCourseProfile::DISK)->assertExists('courses/a-course/profile.json');
});

it('bumps the version when the profile is replaced', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $course = Course::factory()->create(['slug' => 'a-course', 'version' => 3]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm(['profile_file' => UploadedFile::fake()->createWithContent('profile.json', profileJson(override: ['distance_m' => 6000]))])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($course->refresh()->version)->toBe(4)->and($course->distance_m)->toBe(6000);
});
