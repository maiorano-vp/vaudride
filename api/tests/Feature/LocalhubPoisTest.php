<?php

declare(strict_types=1);

use App\Actions\ImportLocalhubPois;
use App\Actions\StoreCourseProfile;
use App\Enums\PoiCategory;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\PoisRelationManager;
use App\Models\Course;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A straight 10 km road going east from (46.5, 6.6), a point every 50 m. */
function longEastboundProfile(): string
{
    $points = [];
    for ($d = 0; $d <= 10_000; $d += 50) {
        $points[] = ['d' => $d, 'lat' => 46.5, 'lon' => 6.6 + $d / (111_320 * cos(deg2rad(46.5))), 'ele' => 500, 'grade' => 0];
    }

    return json_encode(['points' => $points], JSON_THROW_ON_ERROR);
}

/** A Local Hub hit `metres` along the road and `northM` metres north of it. */
function hit(string $id, string $name, string $category, int $metres, int $northM = 50, array $extra = []): array
{
    return [
        'id' => $id,
        'name' => ['fr' => $name, 'de' => $name.' (de)'],
        'summary' => ['fr' => 'Un lieu à découvrir.'],
        'assetCategory' => ['slug' => $category, 'name' => ['fr' => $category]],
        'location' => ['lat' => 46.5 + $northM / 111_320, 'lng' => 6.6 + $metres / (111_320 * cos(deg2rad(46.5))), 'address' => 'Rue du Lac 1', 'postcode' => '1800', 'city' => 'Vevey', 'website' => 'https://example.org/'.$id],
        ...$extra,
    ];
}

function fakeLocalhub(array $hits, int $pages = 1): void
{
    Http::fake(['localhub.test/*' => Http::response(['status' => 'success', 'data' => ['pois' => ['hits' => $hits, 'totalPages' => $pages, 'page' => 1]]])]);
}

beforeEach(function (): void {
    Storage::fake(StoreCourseProfile::DISK);
    config(['services.localhub.url' => 'https://localhub.test', 'services.localhub.token' => 'secret-token']);
    $this->course = Course::factory()->create(['slug' => 'a-course']);
    Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), longEastboundProfile());
});

it('imports the places along the road and the lodging around the ends, unpublished', function (): void {
    fakeLocalhub([
        hit('1', 'Cave du Lac', 'cave-et-vin', 4000),
        hit('2', 'Hôtel du Port', 'hebergement', 800, northM: 1500), // 1.7 km from the start
        hit('3', 'Auberge de la Gare', 'hebergement', 9500, northM: 400), // near the finish
        hit('4', 'Hôtel lointain', 'hebergement', 5000, northM: 200), // far from both ends
        hit('5', 'Musée loin de la route', 'musee', 3000, northM: 2000), // too far from the road
        hit('6', 'Fromagerie', 'terroir', 6000, northM: 200),
    ]);

    $result = resolve(ImportLocalhubPois::class)->handle($this->course);

    expect($result)->toBe(['created' => 4, 'skipped' => 2]);
    $pois = $this->course->pois()->get()->keyBy('external_id');
    expect($pois->keys()->sort()->values()->all())->toBe(['localhub:1', 'localhub:2', 'localhub:3', 'localhub:6'])
        ->and($pois['localhub:1']->category)->toBe(PoiCategory::Wine)
        ->and($pois['localhub:1']->title)->toBe(['fr' => 'Cave du Lac', 'de' => 'Cave du Lac (de)', 'en' => 'Cave du Lac'])
        ->and($pois['localhub:1']->distance_m)->toBeBetween(3950, 4050)
        ->and($pois['localhub:1']->url)->toBe('https://example.org/1')
        ->and($pois['localhub:1']->address)->toBe('Rue du Lac 1, 1800 Vevey')
        ->and($pois['localhub:1']->source)->toBe('localhub')
        ->and($pois['localhub:1']->published)->toBeFalse()
        ->and($pois['localhub:2']->category)->toBe(PoiCategory::Lodging)
        ->and($pois['localhub:6']->category)->toBe(PoiCategory::Food);
});

it('sends the bearer token and asks for the places page by page', function (): void {
    fakeLocalhub([hit('1', 'Cave du Lac', 'vin', 4000)], pages: 1);

    resolve(ImportLocalhubPois::class)->handle($this->course);

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret-token')
        && str_starts_with($r->url(), 'https://localhub.test/api/v1/pois')
        && $r['page'] === 1 && $r['pageSize'] === 100);
});

it('follows the pagination and does not import a place twice', function (): void {
    Http::fake(['localhub.test/*' => Http::sequence()
        ->push(['data' => ['pois' => ['hits' => [hit('1', 'Cave du Lac', 'vin', 4000)], 'totalPages' => 2]]])
        ->push(['data' => ['pois' => ['hits' => [hit('2', 'Belvédère', 'panorama', 6000)], 'totalPages' => 2]]])
        ->push(['data' => ['pois' => ['hits' => [hit('1', 'Cave du Lac', 'vin', 4000)], 'totalPages' => 1]]]),
    ]);

    expect(resolve(ImportLocalhubPois::class)->handle($this->course)['created'])->toBe(2);

    Http::fake(['localhub.test/*' => Http::response(['data' => ['pois' => ['hits' => [hit('1', 'Cave du Lac', 'vin', 4000)], 'totalPages' => 1]]])]);

    expect(resolve(ImportLocalhubPois::class)->handle($this->course))->toBe(['created' => 0, 'skipped' => 1])
        ->and($this->course->pois()->count())->toBe(2);
});

it('copes with a name that is a plain string and coordinates in a flat or geo form', function (): void {
    $flat = ['id' => '7', 'name' => 'Fontaine', 'assetCategory' => ['slug' => 'fontaine'], '_geo' => ['lat' => 46.5003, 'lng' => 6.6 + 3000 / (111_320 * cos(deg2rad(46.5)))]];
    $none = ['id' => '8', 'name' => 'Sans position'];
    fakeLocalhub([$flat, $none]);

    $result = resolve(ImportLocalhubPois::class)->handle($this->course);

    expect($result)->toBe(['created' => 1, 'skipped' => 1])
        ->and($this->course->pois()->firstOrFail()->title)->toBe(['fr' => 'Fontaine', 'de' => 'Fontaine', 'en' => 'Fontaine'])
        ->and($this->course->pois()->firstOrFail()->category)->toBe(PoiCategory::Water);
});

it('returns null for a course without a profile and sends nothing', function (): void {
    Http::fake();

    expect(resolve(ImportLocalhubPois::class)->handle(Course::factory()->create()))->toBeNull();
    Http::assertNothingSent();
});

it('shows the first place as the API gives it, and complains without a token', function (): void {
    fakeLocalhub([hit('1', 'Cave du Lac', 'vin', 4000)]);

    $this->artisan('localhub:inspect')->expectsOutputToContain('Fields: id, name, summary, assetCategory, location')->assertSuccessful();

    config(['services.localhub.token' => '']);
    $this->artisan('localhub:inspect')->expectsOutputToContain('LOCALHUB_TOKEN is not set')->assertFailed();
});

it('imports from the command line', function (): void {
    fakeLocalhub([hit('1', 'Cave du Lac', 'vin', 4000)]);

    $this->artisan('localhub:import', ['slug' => 'a-course'])->expectsOutputToContain('1 imported')->assertSuccessful();
    $this->artisan('localhub:import', ['slug' => 'nope'])->assertFailed();
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('imports with a button, only when a token is set', function (): void {
        fakeLocalhub([hit('1', 'Cave du Lac', 'vin', 4000)]);

        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->callTableAction('importLocalhub')
            ->assertNotified('1 lieu(x) importé(s), 0 ignoré(s). À relire avant publication.');

        config(['services.localhub.token' => '']);

        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->assertTableActionHidden('importLocalhub');
    });

    it('says so when Local Hub refuses the token', function (): void {
        Http::fake(['localhub.test/*' => Http::response(['message' => 'Forbidden'], 403)]);

        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->callTableAction('importLocalhub')
            ->assertNotified('Local Hub ne répond pas ou refuse le jeton (LOCALHUB_TOKEN).');
    });
});
