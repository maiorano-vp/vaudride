<?php

declare(strict_types=1);

use App\Actions\ImportOsmPois;
use App\Actions\StoreCourseProfile;
use App\Enums\PoiCategory;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\PoisRelationManager;
use App\Models\Course;
use App\Models\Poi;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A straight 2 km road going east from (46.5, 6.6), a point every 5 m. */
function eastboundProfile(): string
{
    $points = [];
    for ($d = 0; $d <= 2000; $d += 5) {
        $points[] = ['d' => $d, 'lat' => 46.5, 'lon' => 6.6 + $d / (111_320 * cos(deg2rad(46.5))), 'ele' => 500, 'grade' => 0];
    }

    return json_encode(['points' => $points], JSON_THROW_ON_ERROR);
}

/** An OSM node `offsetNorthM` metres north of the road, `metres` metres from the start. */
function osmNode(int $id, array $tags, int $metres, int $offsetNorthM = 20): array
{
    return [
        'type' => 'node', 'id' => $id, 'tags' => $tags,
        'lat' => 46.5 + $offsetNorthM / 111_320,
        'lon' => 6.6 + $metres / (111_320 * cos(deg2rad(46.5))),
    ];
}

beforeEach(function (): void {
    Storage::fake(StoreCourseProfile::DISK);
    config(['services.overpass.url' => 'https://overpass.test/api']);
});

it('maps OSM tags to categories and ignores the rest', function (array $tags, ?PoiCategory $expected): void {
    expect(PoiCategory::fromOsmTags($tags))->toBe($expected);
})->with([
    'viewpoint' => [['tourism' => 'viewpoint'], PoiCategory::Viewpoint],
    'museum' => [['tourism' => 'museum'], PoiCategory::Culture],
    'castle' => [['historic' => 'castle'], PoiCategory::History],
    'peak' => [['natural' => 'peak'], PoiCategory::Nature],
    'fountain' => [['amenity' => 'drinking_water'], PoiCategory::Water],
    'winery' => [['craft' => 'winery'], PoiCategory::Wine],
    'cafe' => [['amenity' => 'cafe'], PoiCategory::Food],
    'bench' => [['amenity' => 'bench'], null],
    'no tags' => [[], null],
]);

it('imports the places along the course as unpublished points, placed on the road', function (): void {
    $course = Course::factory()->create(['slug' => 'a-course']);
    Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), eastboundProfile());
    Http::fake(['overpass.test/*' => Http::response(['elements' => [
        osmNode(1, ['tourism' => 'viewpoint', 'name' => 'Belvédère', 'name:de' => 'Aussichtspunkt', 'website' => 'https://example.org'], 1000),
        osmNode(2, ['amenity' => 'drinking_water'], 500),
        osmNode(3, ['historic' => 'castle', 'name' => 'Château lointain'], 800, offsetNorthM: 900), // too far from the road
        osmNode(4, ['amenity' => 'bench'], 300), // not a place worth showing
        ['type' => 'way', 'id' => 7, 'center' => ['lat' => 46.5001, 'lon' => 6.6 + 1500 / (111_320 * cos(deg2rad(46.5)))], 'tags' => ['craft' => 'winery', 'name' => 'Cave du Lac', 'wikipedia' => 'fr:Cave du Lac']],
    ]])]);

    $created = resolve(ImportOsmPois::class)->handle($course);

    expect($created)->toBe(3);
    $pois = $course->pois()->get()->keyBy('osm_id');
    expect($pois['node/1']->title)->toBe(['fr' => 'Belvédère', 'de' => 'Aussichtspunkt', 'en' => 'Belvédère'])
        ->and($pois['node/1']->distance_m)->toBeBetween(995, 1005)
        ->and($pois['node/1']->url)->toBe('https://example.org')
        ->and($pois['node/1']->category)->toBe(PoiCategory::Viewpoint)
        ->and($pois['node/1']->source)->toBe('osm')
        ->and($pois['node/1']->published)->toBeFalse()
        ->and($pois['node/2']->title['fr'])->toBe('Fontaine')
        ->and($pois['way/7']->url)->toBe('https://fr.wikipedia.org/wiki/Cave_du_Lac');
    Http::assertSent(fn ($request) => str_contains($request['data'], 'around:120,') && str_contains($request->header('User-Agent')[0], 'VaudRide'));
});

it('does not import the same place twice', function (): void {
    $course = Course::factory()->create(['slug' => 'a-course']);
    Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), eastboundProfile());
    Http::fake(['overpass.test/*' => Http::response(['elements' => [osmNode(1, ['tourism' => 'viewpoint', 'name' => 'Belvédère'], 1000)]])]);

    expect(resolve(ImportOsmPois::class)->handle($course))->toBe(1)
        ->and(resolve(ImportOsmPois::class)->handle($course))->toBe(0)
        ->and($course->pois()->count())->toBe(1);
});

it('returns null for a course without a profile', function (): void {
    Http::fake();

    expect(resolve(ImportOsmPois::class)->handle(Course::factory()->create()))->toBeNull();
    Http::assertNothingSent();
});

it('only shows published points on the public course', function (): void {
    $course = Course::factory()->create(['slug' => 'a-course']);
    Poi::factory()->for($course)->create(['title' => ['fr' => 'Visible'], 'published' => true, 'category' => PoiCategory::Wine]);
    Poi::factory()->for($course)->create(['title' => ['fr' => 'À relire'], 'published' => false, 'source' => 'osm']);

    $this->getJson('/api/v1/courses/a-course')
        ->assertOk()
        ->assertJsonCount(1, 'data.pois')
        ->assertJsonPath('data.pois.0.title.fr', 'Visible')
        ->assertJsonPath('data.pois.0.category', 'wine');
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('imports from OpenStreetMap with a button', function (): void {
        $course = Course::factory()->create(['slug' => 'a-course']);
        Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), eastboundProfile());
        Http::fake(['overpass.test/*' => Http::response(['elements' => [osmNode(1, ['tourism' => 'viewpoint', 'name' => 'Belvédère'], 1000)]])]);

        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('importOsm')
            ->assertNotified('1 lieu(x) importé(s), à relire avant publication.');

        expect($course->pois()->where('source', 'osm')->count())->toBe(1);
    });

    it('says so when OpenStreetMap is down', function (): void {
        $course = Course::factory()->create(['slug' => 'a-course']);
        Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), eastboundProfile());
        Http::fake(['overpass.test/*' => Http::response('busy', 504)]);

        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('importOsm')
            ->assertNotified('OpenStreetMap ne répond pas, réessayez dans quelques minutes.');
    });
});
