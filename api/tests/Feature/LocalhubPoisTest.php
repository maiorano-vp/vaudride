<?php

declare(strict_types=1);

use App\Actions\ImportLocalhubPois;
use App\Actions\StoreCourseProfile;
use App\Actions\SyncLocalhubPlaces;
use App\Enums\PoiCategory;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\PoisRelationManager;
use App\Models\Course;
use App\Models\LocalhubPlace;
use App\Models\User;
use App\Services\LocalhubPlaceMapper;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
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

/** An attribute the way the API gives it: a label, a type and a value (a flag, or the chosen options). */
function attr(string $label, bool|array $value = true): array
{
    $options = is_array($value) ? array_map(fn (string $name): array => ['key' => 'k', 'json' => ['value' => 'k', 'label' => ['fr' => $name]]], $value) : true;

    return ['label' => ['fr' => $label, 'de' => $label], 'type' => is_array($value) ? 'multiselect' : 'boolean', 'value' => $options];
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
    Http::swap(new Factory); // a later fake would otherwise lose to the first one registered
    Http::fake(['localhub.test/*' => Http::response(['status' => 'success', 'data' => ['pois' => ['hits' => $hits, 'totalPages' => $pages, 'page' => 1]]])]);
}

/** What a sync would store, from hits as the API gives them. */
function syncHits(array $hits, int $pages = 1): array
{
    fakeLocalhub($hits, $pages);

    return resolve(SyncLocalhubPlaces::class)->handle();
}

beforeEach(function (): void {
    Storage::fake(StoreCourseProfile::DISK);
    config(['services.localhub.url' => 'https://localhub.test', 'services.localhub.token' => 'secret-token']);
    $this->course = Course::factory()->create(['slug' => 'a-course']);
    Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), longEastboundProfile());
});

describe('syncing the catalogue', function (): void {
    it('keeps a compact copy of every place that has a position', function (): void {
        $result = syncHits([
            hit('1', 'Cave du Lac', 'cave-et-vin', 4000, extra: ['attributes' => ['a' => attr('Certifié Vaud Oenotourisme')]]),
            ['id' => '2', 'name' => 'Sans position'],
        ]);

        expect($result)->toBe(['synced' => 1, 'without_position' => 1, 'ignored' => 0, 'removed' => 0]);
        $place = LocalhubPlace::query()->findOrFail('localhub:1');
        expect($place->title)->toBe(['fr' => 'Cave du Lac', 'de' => 'Cave du Lac (de)', 'en' => 'Cave du Lac'])
            ->and($place->category)->toBe(PoiCategory::Wine)
            ->and($place->url)->toBe('https://example.org/1')
            ->and($place->address)->toBe('Rue du Lac 1, 1800 Vevey')
            ->and($place->lat)->toBeFloat();
    });

    it('keeps very long links whole', function (): void {
        $long = 'https://example.org/'.str_repeat('a', 700);
        syncHits([hit('1', 'Lien long', '', 1000, extra: ['links' => [['url' => $long]], 'location' => ['lat' => 46.5, 'lng' => 6.61]])]);

        expect(LocalhubPlace::query()->findOrFail('localhub:1')->url)->toBe($long);
        resolve(ImportLocalhubPois::class)->handle($this->course);
        expect($this->course->pois()->firstOrFail()->url)->toBe($long);
    });

    it('sends the bearer token and asks only for the fields it reads, page by page', function (): void {
        syncHits([hit('1', 'Cave du Lac', 'vin', 4000)]);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret-token')
            && str_starts_with($r->url(), 'https://localhub.test/api/v1/pois')
            && $r['page'] === 1 && $r['pageSize'] === 100 && $r['fields'] === 'name,summary,location,links,attributes,assetCategory,rootAssetCategory');
    });

    it('follows the pagination', function (): void {
        Http::swap(new Factory);
        Http::fake(['localhub.test/*' => Http::sequence()
            ->push(['data' => ['pois' => ['hits' => [hit('1', 'A', 'x', 100)], 'totalPages' => 2]]])
            ->push(['data' => ['pois' => ['hits' => [hit('2', 'B', 'x', 200)], 'totalPages' => 2]]]),
        ]);

        expect(resolve(SyncLocalhubPlaces::class)->handle()['synced'])->toBe(2)->and(LocalhubPlace::query()->count())->toBe(2);
    });

    it('updates what changed and removes what left the catalogue', function (): void {
        syncHits([hit('1', 'Ancien nom', 'x', 100), hit('2', 'Disparu', 'x', 200)]);
        $this->travel(1)->minute();

        $result = syncHits([hit('1', 'Nouveau nom', 'x', 100)]);

        expect($result['removed'])->toBe(1)
            ->and(LocalhubPlace::query()->get()->mapWithKeys(fn (LocalhubPlace $p): array => [$p->id => $p->title['fr']])->all())->toBe(['localhub:1' => 'Nouveau nom']);
    });

    it('keeps the previous copy when a pass fails half way', function (): void {
        syncHits([hit('1', 'Garde', 'x', 100)]);
        $this->travel(1)->minute();
        Http::swap(new Factory);
        Http::fake(['localhub.test/*' => Http::sequence()
            ->push(['data' => ['pois' => ['hits' => [hit('9', 'Nouveau', 'x', 100)], 'totalPages' => 2]]])
            ->push('boom', 500)
            ->push('boom', 500)
            ->push('boom', 500),
        ]);

        expect(fn () => resolve(SyncLocalhubPlaces::class)->handle())->toThrow(RequestException::class);
        expect(LocalhubPlace::query()->where('id', 'localhub:1')->exists())->toBeTrue();
    });

    it('reads the category from the attributes an editor ticked, and links given per language', function (): void {
        $real = hit('9', 'Église', '', 2000, extra: [
            'attributes' => [
                'a' => attr('Thématique', ['Patrimoine religieux', 'Familial']),
                'b' => ['label' => ['fr' => 'Accessible en fauteuil roulant'], 'type' => 'boolean', 'value' => true],
                'c' => ['label' => ['fr' => 'Sous-destination'], 'type' => 'multiselect', 'value' => null],
            ],
            'links' => [['label' => ['en' => 'Tickets'], 'url' => ['de' => 'https://example.org/de', 'fr' => 'https://example.org/fr']]],
        ]);
        $real['location']['lat'] = (string) $real['location']['lat']; // the API sends coordinates as strings
        $real['location']['lng'] = (string) $real['location']['lng'];
        unset($real['location']['website']);
        syncHits([$real]);

        $place = LocalhubPlace::query()->findOrFail('localhub:9');
        expect($place->category)->toBe(PoiCategory::History)->and($place->url)->toBe('https://example.org/fr');
    });

    it('does not take the words of an accessibility attribute for a category', function (): void {
        syncHits([hit('1', 'Lieu', '', 1000, extra: ['attributes' => ['a' => attr('Partiellement accessible en fauteuil roulant'), 'b' => attr('Restauration sur place')]])]);

        expect(LocalhubPlace::query()->findOrFail('localhub:1')->category)->toBe(PoiCategory::Culture);
    });

    it('copes with a name that is a plain string and a geo position', function (): void {
        $flat = ['id' => '7', 'name' => 'Belvédère', 'attributes' => ['a' => attr('Panorama / belvédère')], '_geo' => ['lat' => 46.5003, 'lng' => 6.64]];
        syncHits([$flat]);

        $place = LocalhubPlace::query()->findOrFail('localhub:7');
        expect($place->title)->toBe(['fr' => 'Belvédère', 'de' => 'Belvédère', 'en' => 'Belvédère'])->and($place->category)->toBe(PoiCategory::Viewpoint);
    });

    it('shows the first place as the API gives it, and complains without a token', function (): void {
        fakeLocalhub([hit('1', 'Cave du Lac', 'vin', 4000)]);

        $this->artisan('localhub:inspect')->expectsOutputToContain('Fields: id, name, summary, assetCategory, location')->assertSuccessful();

        config(['services.localhub.token' => '']);
        $this->artisan('localhub:inspect')->expectsOutputToContain('LOCALHUB_TOKEN is not set')->assertFailed();
        $this->artisan('localhub:sync')->expectsOutputToContain('LOCALHUB_TOKEN is not set')->assertFailed();
    });

    it('syncs from the command line', function (): void {
        fakeLocalhub([hit('1', 'Cave du Lac', 'vin', 4000)]);

        $this->artisan('localhub:sync')->expectsOutputToContain('1 places synced')->assertSuccessful();
    });
});

describe('importing a course from the copy', function (): void {
    it('imports the places along the road and the lodging around the ends, unpublished', function (): void {
        syncHits([
            hit('1', 'Cave du Lac', 'cave-et-vin', 4000, extra: ['attributes' => ['a' => attr('Certifié Vaud Oenotourisme')]]),
            hit('2', 'Hôtel du Port', 'hebergement', 800, northM: 1500), // 1.7 km from the start
            hit('3', 'Auberge de la Gare', 'hebergement', 9500, northM: 400), // near the finish
            hit('4', 'Hôtel lointain', 'hebergement', 5000, northM: 200), // far from both ends
            hit('5', 'Musée loin de la route', 'musee', 3000, northM: 2000), // too far from the road
            hit('6', 'Fromagerie', 'terroir', 6000, northM: 200, extra: ['attributes' => ['a' => attr('Type d\'établissement', ['Restaurant / Brasserie'])]]),
        ]);

        $result = resolve(ImportLocalhubPois::class)->handle($this->course);

        expect($result)->toMatchArray(['created' => 4, 'skipped' => 2]);
        $pois = $this->course->pois()->get()->keyBy('external_id');
        expect($pois->keys()->sort()->values()->all())->toBe(['localhub:1', 'localhub:2', 'localhub:3', 'localhub:6'])
            ->and($pois['localhub:1']->category)->toBe(PoiCategory::Wine)
            ->and($pois['localhub:1']->title['de'])->toBe('Cave du Lac (de)')
            ->and($pois['localhub:1']->description)->toBe(['fr' => 'Un lieu à découvrir.', 'de' => 'Un lieu à découvrir.', 'en' => 'Un lieu à découvrir.'])
            ->and($pois['localhub:1']->distance_m)->toBeBetween(3950, 4050)
            ->and($pois['localhub:1']->url)->toBe('https://example.org/1')
            ->and($pois['localhub:1']->address)->toBe('Rue du Lac 1, 1800 Vevey')
            ->and($pois['localhub:1']->source)->toBe('localhub')
            ->and($pois['localhub:1']->published)->toBeFalse()
            ->and($pois['localhub:2']->category)->toBe(PoiCategory::Lodging)
            ->and($pois['localhub:6']->category)->toBe(PoiCategory::Food);
    });

    it('does not import the same place twice', function (): void {
        syncHits([hit('1', 'Cave du Lac', 'vin', 4000)]);

        expect(resolve(ImportLocalhubPois::class)->handle($this->course)['created'])->toBe(1)
            ->and(resolve(ImportLocalhubPois::class)->handle($this->course))->toMatchArray(['created' => 0, 'skipped' => 1])
            ->and($this->course->pois()->count())->toBe(1);
    });

    it('lists what it would import without writing anything', function (): void {
        syncHits([hit('1', 'Cave du Lac', 'vin', 4000), hit('2', 'Loin', 'vin', 4000, northM: 5000)]);

        $result = resolve(ImportLocalhubPois::class)->handle($this->course, dryRun: true);

        expect($result['created'])->toBe(1)
            ->and($result['preview'][0])->toMatchArray(['title' => 'Cave du Lac', 'category' => 'wine'])
            ->and($this->course->pois()->count())->toBe(0);

        $this->artisan('localhub:import', ['slug' => 'a-course', '--dry-run' => true])->expectsOutputToContain('Nothing was written')->assertSuccessful();
    });

    it('imports from the command line', function (): void {
        syncHits([hit('1', 'Cave du Lac', 'vin', 4000)]);

        $this->artisan('localhub:import', ['slug' => 'a-course'])->expectsOutputToContain('1 imported')->assertSuccessful();
        $this->artisan('localhub:import', ['slug' => 'nope'])->assertFailed();
    });

    it('can publish what it imports, for local development', function (): void {
        syncHits([hit('1', 'Cave du Lac', 'vin', 4000)]);

        $this->artisan('localhub:import', ['slug' => 'a-course', '--publish' => true])->assertSuccessful();

        expect($this->course->pois()->firstOrFail()->published)->toBeTrue();
    });

    it('has nothing to import before the first sync or without a profile', function (): void {
        expect(resolve(ImportLocalhubPois::class)->handle($this->course))->toBeNull();

        syncHits([hit('1', 'Cave du Lac', 'vin', 4000)]);

        expect(resolve(ImportLocalhubPois::class)->handle(Course::factory()->create()))->toBeNull();
    });
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('imports with a button', function (): void {
        syncHits([hit('1', 'Cave du Lac', 'vin', 4000)]);

        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->callTableAction('importLocalhub')
            ->assertNotified('1 lieu(x) importé(s), 0 ignoré(s). À relire avant publication.');
    });

    it('explains what to do when the catalogue was never synced', function (): void {
        Livewire::test(PoisRelationManager::class, ['ownerRecord' => $this->course, 'pageClass' => EditCourse::class])
            ->callTableAction('importLocalhub')
            ->assertNotified('Rien à importer: le parcours n’a pas de profil, ou le catalogue Local Hub n’a jamais été synchronisé (php artisan localhub:sync).');
    });
});

describe('categories', function (): void {
    it('reads the category of a place from its name', function (string $title, PoiCategory $expected): void {
        expect(resolve(LocalhubPlaceMapper::class)->categorize($title, []))->toBe($expected);
    })->with([
        'hotel' => ['Hôtel Les Trois Sapins', PoiCategory::Lodging],
        'camping' => ['Camping de La Pichette', PoiCategory::Lodging],
        'town hall is not a hotel' => ['Hôtel de Ville de Vevey', PoiCategory::Culture],
        'apartments' => ['Visionapartments', PoiCategory::Culture], // one word: no guess
        'inn' => ['Auberge de la Gare', PoiCategory::Lodging],
        'station' => ['Gare de Chexbres', PoiCategory::Station],
        'a station name later in the title is not a station' => ['Café de la Gare', PoiCategory::Food],
        'pharmacy' => ['Pharmacieplus du Marché Vevey', PoiCategory::Culture], // glued words: no guess either
        'supermarket' => ['Migros Vevey', PoiCategory::Shop],
        'butcher' => ['Boucherie-Charcuterie Stuby', PoiCategory::Shop],
        'wine cellar' => ['Caveau des Vignerons d\'Epesses', PoiCategory::Wine],
        'domain' => ['Domaine du Burignon', PoiCategory::Wine],
        'restaurant' => ['Restaurant Hôtel du Marchairuz', PoiCategory::Lodging], // the hotel wins: it sleeps people
        'bistro' => ['Le Bistrot du Rivage', PoiCategory::Food],
        'bakery' => ['Boulangerie Bidlingmeyer Chexbres', PoiCategory::Food],
        'church' => ['Eglise de Saint-Saphorin', PoiCategory::History],
        'viewpoint' => ['Photo spot Grand Tour Lavaux', PoiCategory::Viewpoint],
        'beach' => ['Plage des Curtinaux', PoiCategory::Nature],
        'fountain' => ['Fontaine de la place', PoiCategory::Water],
        'bike shop' => ['Cycles Dupont', PoiCategory::Bike],
        'theatre' => ['Théâtre Grenette', PoiCategory::Culture],
        'glacier is not an ice-cream shop' => ['Glacier 3000', PoiCategory::Culture],
    ]);

    it('lets the ticked attributes decide when the name says nothing', function (): void {
        $mapper = resolve(LocalhubPlaceMapper::class);

        expect($mapper->categorize('Le Kuklos', [['label' => 'type d\'établissement', 'values' => ['restaurant / brasserie']]]))->toBe(PoiCategory::Food)
            ->and($mapper->categorize('Les 11 Terres', [['label' => 'certifié vaud oenotourisme', 'values' => []]]))->toBe(PoiCategory::Wine)
            ->and($mapper->categorize('Le Kuklos', [['label' => 'panorama / belvédère', 'values' => []]]))->toBe(PoiCategory::Viewpoint)
            ->and($mapper->categorize('Un lieu', [['label' => 'partiellement accessible en fauteuil roulant', 'values' => []]]))->toBe(PoiCategory::Culture);
    });

    it('stores the facts and re-applies new rules without reading the API again', function (): void {
        syncHits([hit('1', 'Lieu', '', 1000, extra: ['attributes' => ['a' => attr('Panorama / belvédère')]])]);
        $place = LocalhubPlace::query()->findOrFail('localhub:1');
        expect($place->category)->toBe(PoiCategory::Viewpoint)
            ->and(collect($place->facts)->pluck('label')->all())->toContain('panorama / belvédère');

        $place->forceFill(['category' => PoiCategory::Culture])->save();
        Http::fake(); // nothing may be sent

        $this->artisan('localhub:recategorize')->expectsOutputToContain('1 places changed category')->assertSuccessful();

        expect($place->refresh()->category)->toBe(PoiCategory::Viewpoint);
        Http::assertNothingSent();
    });
});

describe('Local Hub categories', function (): void {
    /** A place given a root and a sub-category the way the API does. */
    function categorized(string $name, string $root, string $sub): array
    {
        return hit(md5($name.$sub), $name, '', 1000, extra: ['rootAssetCategory' => ['slug' => $root, 'name' => ['fr' => $root]], 'assetCategory' => ['slug' => $sub, 'name' => ['fr' => $sub]]]);
    }

    it('uses the official sub-category, whatever the name says', function (string $root, string $sub, string $name, PoiCategory $expected): void {
        syncHits([categorized($name, $root, $sub)]);

        expect(LocalhubPlace::query()->firstOrFail()->category)->toBe($expected);
    })->with([
        'restaurant' => ['catering', 'restaurants', 'Chez Antoine', PoiCategory::Food],
        'wine' => ['catering', 'gastronomy_wine', 'Les 11 Terres', PoiCategory::Wine],
        'local products' => ['catering', 'otv_localprod', 'Fromagerie du coin', PoiCategory::Food],
        'hotel' => ['accommodation', 'hotel', 'Le Rivage', PoiCategory::Lodging],
        'holiday flat' => ['accommodation', 'holiday_apartment', 'Visionapartments', PoiCategory::Lodging],
        'campsite' => ['accommodation', 'campsite', 'La Pichette', PoiCategory::Lodging],
        'hotel listed under practical information' => ['practical-information', 'otv_hotels', 'Chez Nous', PoiCategory::Lodging],
        'shopping' => ['practical-information', 'otv_shopping', 'Pharmacieplus du Marché Vevey', PoiCategory::Shop],
        'practical infrastructure' => ['practical-information', 'otv_infra', 'Parking du Port', PoiCategory::Service],
        'transport' => ['practical-information', 'transports', 'Arrêt', PoiCategory::Station],
        'museum' => ['culture', 'otv_museums', 'Musée Jenisch', PoiCategory::Culture],
        'heritage' => ['culture', 'culture_heritage', 'Grand-Rue', PoiCategory::Culture],
        'sports' => ['activities', 'sports_leisure', 'Swiss Paddle', PoiCategory::Activity],
        'nature' => ['nature', 'nature', 'Lac de Bret', PoiCategory::Nature],
        'tourist train' => ['nature', 'tourist_transports', 'Le train MBC', PoiCategory::Activity],
    ]);

    it('lets the name refine a generic category', function (string $root, string $sub, string $name, PoiCategory $expected): void {
        syncHits([categorized($name, $root, $sub)]);

        expect(LocalhubPlace::query()->firstOrFail()->category)->toBe($expected);
    })->with([
        'a church among heritage' => ['culture', 'culture_heritage', 'Eglise de Saint-Saphorin', PoiCategory::History],
        'a viewpoint among activities' => ['activities', 'sports_leisure', 'Belvédère du Signal', PoiCategory::Viewpoint],
        'a beach among activities' => ['activities', 'otv_summeracti', 'Plage des Curtinaux', PoiCategory::Nature],
        'a station among heritage' => ['culture', 'culture_heritage', 'Gare de Chexbres', PoiCategory::Station],
    ]);

    it('keeps the official category over a name that points elsewhere', function (): void {
        syncHits([categorized('Hôtel de Ville', 'catering', 'restaurants')]);

        expect(LocalhubPlace::query()->firstOrFail()->category)->toBe(PoiCategory::Food);
    });

    it('does not keep what is not for riders, and says so', function (): void {
        $result = syncHits([categorized('Ecole de Lutry', 'practical-information', 'otv_schools'), categorized('Cave', 'catering', 'gastronomy_wine')]);

        expect($result)->toMatchArray(['synced' => 1, 'ignored' => 1])->and(LocalhubPlace::query()->count())->toBe(1);
    });

    it('falls back to the name and the attributes when Local Hub gave no category', function (): void {
        syncHits([hit('1', 'Hôtel Les Trois Sapins', '', 1000), categorized('Le Café littéraire', 'j3l_poi_notlisted', 'j3l_poi_notlisted')]);

        expect(LocalhubPlace::query()->get()->pluck('category')->map->value->sort()->values()->all())->toBe(['food', 'lodging']);
    });
});
