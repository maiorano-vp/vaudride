<?php

declare(strict_types=1);

use App\Actions\ImportTerroirPois;
use App\Actions\StoreCourseProfile;
use App\Enums\PoiCategory;
use App\Models\Course;
use App\Services\TerroirPartnerMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** A straight 10 km road going east from (46.5, 6.6), a point every 50 m. */
function terroirRoadProfile(): string
{
    $points = [];
    for ($d = 0; $d <= 10_000; $d += 50) {
        $points[] = ['d' => $d, 'lat' => 46.5, 'lon' => 6.6 + $d / (111_320 * cos(deg2rad(46.5))), 'ele' => 500, 'grade' => 0];
    }

    return json_encode(['points' => $points], JSON_THROW_ON_ERROR);
}

/** A partner as the Terroir API sends it; coordinates are strings. By default 100 m north of the road, 4 km along. */
function partner(int $id, string $title = 'Boulangerie du Lac', array $extra = []): array
{
    return [
        'id' => $id,
        'title' => $title,
        'company' => 'Raison sociale SA',
        'content' => '<p>Pains VAUD CERTIFIE D&#039;ICI</p>',
        'address' => 'Rue du Lac 1',
        'postal_locality' => '1800 Vevey',
        'canton' => 'VD',
        'latitude' => '46.50090',
        'longitude' => (string) (6.6 + 4000 / (111_320 * cos(deg2rad(46.5)))),
        'website' => 'www.boulangerie.test',
        'logo' => 'https://cdn.test/logo.png',
        'cover_image' => 'https://cdn.test/cover.jpg',
        ...$extra,
    ];
}

/** The API answering with these partners per group id; the three groups the import wants always exist. */
function fakeTerroir(array $perGroup): void
{
    Http::fake(function (Request $request) use ($perGroup) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if (str_ends_with((string) $path, '/v1/partners/groups')) {
            return Http::response(['data' => [
                ['id' => 5, 'slug' => 'agritourisme', 'title' => 'Agritourisme'],
                ['id' => 12, 'slug' => 'boulangerie-vaudoise', 'title' => 'Boulangerie vaudoise'],
                ['id' => 6, 'slug' => 'petites-epiceries', 'title' => 'Petites épiceries'],
                ['id' => 99, 'slug' => 'autre', 'title' => 'Un autre type'],
            ]]);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $pages = $perGroup[(int) ($query['group'] ?? 0)] ?? [[]];
        $page = (int) ($query['page'] ?? 1);

        return Http::response(['data' => $pages[$page - 1] ?? [], 'meta' => ['current_page' => $page, 'last_page' => count($pages)]]);
    });
}

beforeEach(function (): void {
    Storage::fake(StoreCourseProfile::DISK);
    config(['services.terroir.url' => 'https://terroir.test/api', 'services.terroir.token' => 'secret-token']);
    $this->course = Course::factory()->create(['slug' => 'a-course']);
    Storage::disk(StoreCourseProfile::DISK)->put(StoreCourseProfile::path('a-course'), terroirRoadProfile());
});

describe('reading the partners', function (): void {
    it('asks for the three groups with the token, and maps each to a category', function (): void {
        fakeTerroir([5 => [[partner(1, 'Ferme du Lac')]], 12 => [[partner(2)]], 6 => [[partner(3, 'Epicerie du village')]]]);

        $places = resolve(ImportTerroirPois::class)->partners();

        expect(collect($places)->pluck('category')->all())->toBe([PoiCategory::Agritourism, PoiCategory::Bakery, PoiCategory::Grocery]);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v1/partners?') && $r->hasHeader('Authorization', 'Bearer secret-token') && $r['group'] === 12 && ! isset($r['canton']));
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'group=99'));
    });

    it('follows the pages and keeps a partner that is in two groups once', function (): void {
        fakeTerroir([12 => [[partner(1)], [partner(2, 'Boulangerie du Pont')]], 6 => [[partner(2, 'Boulangerie du Pont')]]]);

        expect(collect(resolve(ImportTerroirPois::class)->partners())->pluck('id')->all())->toBe(['terroir:1', 'terroir:2']);
    });
});

describe('mapping a partner', function (): void {
    $map = fn (array $p): ?array => (new TerroirPartnerMapper)->map($p, PoiCategory::Bakery);

    it('gives plain text, an address, a link with its scheme and the cover image', function () use ($map): void {
        $place = $map(partner(7));

        expect($place['id'])->toBe('terroir:7')
            ->and($place['title']['fr'])->toBe('Boulangerie du Lac')
            ->and($place['description']['fr'])->toBe("Pains VAUD CERTIFIE D'ICI")
            ->and($place['address'])->toBe('Rue du Lac 1, 1800 Vevey')
            ->and($place['url'])->toBe('https://www.boulangerie.test')
            ->and($place['image_url'])->toBe('https://cdn.test/cover.jpg')
            ->and($place['lat'])->toBe(46.5009);
    });

    it('takes the logo without a cover, the company without a title', function () use ($map): void {
        $place = $map(partner(8, extra: ['title' => null, 'cover_image' => null]));

        expect($place['title']['fr'])->toBe('Raison sociale SA')
            ->and($place['image_url'])->toBe('https://cdn.test/logo.png');
    });

    it('has no image rather than a logo that is only a file name', function () use ($map): void {
        expect($map(partner(8, extra: ['cover_image' => null, 'logo' => 'c0efc55188023e96.png']))['image_url'])->toBeNull()
            ->and($map(partner(8, extra: ['cover_image' => '', 'logo' => null]))['image_url'])->toBeNull();
    });

    it('ignores a partner without a usable position or a name', function () use ($map): void {
        expect($map(partner(9, extra: ['latitude' => null])))->toBeNull()
            ->and($map(partner(9, extra: ['latitude' => '0', 'longitude' => '0'])))->toBeNull()
            ->and($map(partner(9, extra: ['title' => '', 'company' => null])))->toBeNull();
    });
});

describe('importing along a course', function (): void {
    beforeEach(function (): void {
        fakeTerroir([12 => [[
            partner(1),
            partner(2, 'Trop loin', ['latitude' => '46.51']), // about 1.1 km off the road
        ]]]);
        $this->import = resolve(ImportTerroirPois::class);
    });

    it('adds the partners within 300 m of the road, unpublished, with their distance along the course', function (): void {
        $result = $this->import->handle($this->course, $this->import->partners());

        $poi = $this->course->pois()->sole();
        expect($result['created'])->toBe(1)
            ->and($poi->source)->toBe('terroir')
            ->and($poi->external_id)->toBe('terroir:1')
            ->and($poi->published)->toBeFalse()
            ->and($poi->category)->toBe(PoiCategory::Bakery)
            ->and($poi->distance_m)->toBe(4000);
    });

    it('publishes at once when asked', function (): void {
        $this->import->handle($this->course, $this->import->partners(), publish: true);

        expect($this->course->pois()->sole()->published)->toBeTrue();
    });

    it('leaves a place already imported as it is, edits included', function (): void {
        $this->import->handle($this->course, $this->import->partners());
        $this->course->pois()->sole()->update(['title' => ['fr' => 'Titre relu'], 'published' => true]);

        $result = $this->import->handle($this->course, $this->import->partners());

        expect($result['created'])->toBe(0)->and($result['skipped'])->toBe(1)
            ->and($this->course->pois()->sole()->title['fr'])->toBe('Titre relu')
            ->and($this->course->pois()->sole()->published)->toBeTrue();
    });

    it('writes nothing on a dry run, and lists what it would import', function (): void {
        $result = $this->import->handle($this->course, $this->import->partners(), dryRun: true);

        expect($result['created'])->toBe(1)
            ->and($result['preview'][0])->toBe(['title' => 'Boulangerie du Lac', 'category' => 'bakery', 'distance_m' => 4000])
            ->and($this->course->pois()->count())->toBe(0);
    });

    it('does nothing for a course without a profile', function (): void {
        $course = Course::factory()->create(['slug' => 'no-profile']);

        expect($this->import->handle($course, $this->import->partners()))->toBeNull();
    });
});

describe('the command', function (): void {
    it('imports for every course with a single read of the API, as drafts', function (): void {
        fakeTerroir([12 => [[partner(1)]]]);

        $this->artisan('terroir:import')->assertSuccessful();

        expect($this->course->pois()->sole()->published)->toBeFalse();
        Http::assertSentCount(4); // the groups, then one page for each of the three groups
    });

    it('refuses to run without a token', function (): void {
        config(['services.terroir.token' => '']);
        Http::fake();

        $this->artisan('terroir:import')->assertFailed();

        Http::assertNothingSent();
    });

    it('imports a single course with --publish, and nothing with --dry-run', function (): void {
        fakeTerroir([12 => [[partner(1)]]]);

        $this->artisan('terroir:import', ['slug' => 'a-course', '--dry-run' => true])->assertSuccessful();
        expect($this->course->pois()->count())->toBe(0);

        $this->artisan('terroir:import', ['slug' => 'a-course', '--publish' => true])->assertSuccessful();
        expect($this->course->pois()->sole()->published)->toBeTrue();
    });

    it('fails for a course that does not exist', function (): void {
        fakeTerroir([]);

        $this->artisan('terroir:import', ['slug' => 'nope'])->assertFailed();
    });
});
