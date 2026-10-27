<?php

declare(strict_types=1);

use App\Enums\ImportSource;
use App\Models\Course;
use App\Models\CourseImport;
use App\Models\Poi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.localhub.token' => 'secret', 'services.localhub.url' => 'https://localhub.test']);
});

/** A tour as the Local Hub API sends it, with a cover image and the destination that publishes it. */
function cardTour(string $route, string $destination = 'Morges Région Tourisme', string $imageId = 'img-1', string $credit = 'Raphaël Dupertuis'): array
{
    return [
        'route' => $route,
        'is_published' => true,
        'tenant' => ['name' => $destination],
        'images' => [
            ['id' => 'other', 'url' => 'https://cdn.test/other.jpg', 'is_cover' => 0, 'sort' => 1, 'copyright' => 'Someone else'],
            ['id' => $imageId, 'url' => 'https://cdn.test/cover.jpg', 'is_cover' => 1, 'sort' => 2, 'copyright' => $credit],
        ],
    ];
}

/** Local Hub answering with these tours per category slug ('velo', 'mtb'). */
function fakeTours(array $perCategory): void
{
    Http::fake(function (Request $request) use ($perCategory) {
        if (str_contains($request->url(), 'pageSize=1&') || str_ends_with($request->url(), 'pageSize=1')) {
            $categories = array_map(fn (string $slug): array => ['id' => "cat-{$slug}", 'slug' => $slug, 'name' => ['fr' => $slug], 'count' => 1], array_keys($perCategory));

            return Http::response(['data' => ['tours' => ['facetDistribution' => ['categories' => $categories]]]]);
        }

        foreach ($perCategory as $slug => $tours) {
            if (str_contains(urldecode($request->url()), "categories]=cat-{$slug}")) {
                return Http::response(['data' => ['tours' => ['totalPages' => 1, 'hits' => $tours]]]);
            }
        }

        return Http::response(['data' => ['tours' => ['totalPages' => 1, 'hits' => []]]]);
    });
}

/** A course imported from a Local Hub tour, found again by the address of its GPX. */
function courseFromTour(string $slug, string $gpx, array $attributes = []): Course
{
    $course = Course::factory()->create(['slug' => $slug, ...$attributes]);
    CourseImport::factory()->create(['source' => ImportSource::Localhub, 'source_url' => $gpx, 'course_slug' => $slug]);

    return $course;
}

it('gives each course the cover photo, destination and kind of its Local Hub tour', function (): void {
    $course = courseFromTour('morges', 'https://gpx.test/1.gpx');
    fakeTours(['velo' => [cardTour('https://gpx.test/1.gpx')]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();

    $course->refresh();
    expect($course->image_url)->toBe('https://localhub.test/api/image/img-1/scale/800') // the cover, not the first picture
        ->and($course->image_credit)->toBe('Raphaël Dupertuis')
        ->and($course->destination)->toBe('Morges Région Tourisme')
        ->and($course->kind)->toBe('velo');
});

it('keeps what the admin typed, unless forced', function (): void {
    $course = courseFromTour('morges', 'https://gpx.test/1.gpx', ['destination' => 'Ma destination', 'image_url' => 'https://mine.test/photo.jpg']);
    fakeTours(['velo' => [cardTour('https://gpx.test/1.gpx')]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();
    $course->refresh();
    expect($course->destination)->toBe('Ma destination')->and($course->image_url)->toBe('https://mine.test/photo.jpg')
        ->and($course->kind)->toBe('velo'); // what was empty is filled

    $this->artisan('localhub:course-cards', ['--force' => true])->assertSuccessful();
    expect($course->refresh()->destination)->toBe('Morges Région Tourisme');
});

it('writes nothing on a dry run, and says what it would do', function (): void {
    $course = courseFromTour('morges', 'https://gpx.test/1.gpx');
    fakeTours(['velo' => [cardTour('https://gpx.test/1.gpx')]]);

    $this->artisan('localhub:course-cards', ['--dry-run' => true])->expectsOutputToContain('1 would be')->assertSuccessful();

    expect($course->refresh()->image_url)->toBeNull();
});

it('leaves a tour without a course here alone, and counts it', function (): void {
    fakeTours(['velo' => [cardTour('https://gpx.test/unknown.gpx')]]);

    $this->artisan('localhub:course-cards')->expectsOutputToContain('1 without a course here')->assertSuccessful();
});

it('lets the first kind win a course that is in two categories, and sets the other', function (): void {
    $both = courseFromTour('both', 'https://gpx.test/both.gpx');
    $bike = courseFromTour('bike', 'https://gpx.test/bike.gpx');
    fakeTours(['velo' => [cardTour('https://gpx.test/both.gpx')], 'mtb' => [cardTour('https://gpx.test/both.gpx'), cardTour('https://gpx.test/bike.gpx')]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();

    expect($both->refresh()->kind)->toBe('velo')->and($bike->refresh()->kind)->toBe('mtb');
});

it('tells mountain-bike and e-scooter courses by their name, and gives the rest a kind even without a tour', function (): void {
    $vtt = courseFromTour('boucle-vtt', 'https://gpx.test/vtt.gpx', ['name' => ['fr' => 'Boucle VTT des blés']]);
    $scooter = courseFromTour('trot', 'https://gpx.test/trot.gpx', ['name' => ['fr' => 'En trottinette électrique']]);
    $plain = courseFromTour('lavaux', 'https://gpx.test/lavaux.gpx', ['name' => ['fr' => 'Lavaux']]);
    $handMade = Course::factory()->create(['slug' => 'marchairuz', 'name' => ['fr' => 'Col du Marchairuz']]);
    $handMadeVtt = Course::factory()->create(['slug' => 'des-bles-vtt-58', 'name' => ['fr' => 'Boucles des blés 58 km']]);
    fakeTours(['velo' => [cardTour('https://gpx.test/vtt.gpx'), cardTour('https://gpx.test/trot.gpx'), cardTour('https://gpx.test/lavaux.gpx')]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();

    expect($vtt->refresh()->kind)->toBe('mtb')->and($scooter->refresh()->kind)->toBe('scooter')->and($plain->refresh()->kind)->toBe('velo')
        ->and($handMade->refresh()->kind)->toBe('velo')->and($handMadeVtt->refresh()->kind)->toBe('mtb');
});

it('falls back on the photo of one of the course’s places when the tour has none', function (): void {
    $course = Course::factory()->create(['slug' => 'no-tour']);
    Poi::factory()->for($course)->create(['published' => true, 'distance_m' => 900, 'image_url' => 'https://img.test/far.jpg', 'image_credit' => 'Far']);
    Poi::factory()->for($course)->create(['published' => true, 'distance_m' => 100, 'image_url' => 'https://img.test/near.jpg', 'image_credit' => 'Near']);
    Poi::factory()->for($course)->create(['published' => false, 'distance_m' => 10, 'image_url' => 'https://img.test/hidden.jpg']);
    $bare = Course::factory()->create(['slug' => 'bare']);
    fakeTours(['velo' => []]);

    $this->artisan('localhub:course-cards')->assertSuccessful();

    expect($course->refresh()->image_url)->toBe('https://img.test/near.jpg')->and($course->image_credit)->toBe('Near')
        ->and($bare->refresh()->image_url)->toBeNull();
});

it('shows the card fields in the course resource', function (): void {
    Course::factory()->create(['slug' => 'morges', 'image_url' => 'https://img.test/a.jpg', 'image_credit' => 'Credit', 'destination' => 'Morges', 'kind' => 'mtb']);

    $this->getJson('/api/v1/courses')->assertOk()->assertJsonPath('data.0.image_url', 'https://img.test/a.jpg')
        ->assertJsonPath('data.0.image_credit', 'Credit')->assertJsonPath('data.0.destination', 'Morges')->assertJsonPath('data.0.kind', 'mtb');
});

it('keeps every picture of the tour for the course page, the cover first, with their credits', function (): void {
    $course = courseFromTour('morges', 'https://gpx.test/1.gpx');
    fakeTours(['velo' => [cardTour('https://gpx.test/1.gpx')]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();

    expect($course->refresh()->images)->toBe([
        ['url' => 'https://localhub.test/api/image/img-1/scale/800', 'credit' => 'Raphaël Dupertuis'], // the cover, though listed second
        ['url' => 'https://localhub.test/api/image/other/scale/800', 'credit' => 'Someone else'],
    ]);
});

it('keeps no more than twelve pictures, and leaves out what is not a picture', function (): void {
    $course = courseFromTour('morges', 'https://gpx.test/1.gpx');
    $tour = cardTour('https://gpx.test/1.gpx');
    $tour['images'] = [
        ...array_map(fn (int $i): array => ['id' => "p{$i}", 'url' => "https://cdn.test/{$i}.jpg", 'sort' => $i, 'copyright' => ''], range(1, 15)),
        ['id' => 'pdf', 'url' => 'https://cdn.test/leaflet.pdf', 'sort' => 0],
    ];
    fakeTours(['velo' => [$tour]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();

    $images = $course->refresh()->images;
    expect($images)->toHaveCount(12)
        ->and($images[0]['url'])->toBe('https://localhub.test/api/image/p1/scale/800')
        ->and($images[0]['credit'])->toBeNull()
        ->and(collect($images)->pluck('url')->contains(fn (string $url): bool => str_contains($url, '/pdf/')))->toBeFalse();
});

it('does not replace the pictures an admin chose unless forced', function (): void {
    $course = courseFromTour('morges', 'https://gpx.test/1.gpx', ['images' => [['url' => 'https://mine.test/a.jpg', 'credit' => null]]]);
    fakeTours(['velo' => [cardTour('https://gpx.test/1.gpx')]]);

    $this->artisan('localhub:course-cards')->assertSuccessful();
    expect($course->refresh()->images)->toBe([['url' => 'https://mine.test/a.jpg', 'credit' => null]]);

    $this->artisan('localhub:course-cards', ['--force' => true])->assertSuccessful();
    expect($course->refresh()->images)->toHaveCount(2);
});

it('gives an empty gallery, not null, in the course resource', function (): void {
    Course::factory()->create(['slug' => 'bare']);

    $this->getJson('/api/v1/courses')->assertOk()->assertJsonPath('data.0.images', []);
});
