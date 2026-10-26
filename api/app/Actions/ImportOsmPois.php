<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PoiCategory;
use App\Models\Course;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Proposes the OpenStreetMap places along a course as unpublished points of interest, for an admin to review.
 * Data © OpenStreetMap contributors (ODbL): the app credits it wherever such a point is shown.
 */
final readonly class ImportOsmPois
{
    private const int CORRIDOR_M = 120; // how far from the road a place may be

    private const int ROUTE_STEP_M = 250; // spacing of the route points sent to Overpass

    private const int MAX_PER_COURSE = 150;

    /**
     * @return int|null how many points were created, null when the course has no profile yet
     */
    public function handle(Course $course): ?int
    {
        $disk = Storage::disk(StoreCourseProfile::DISK);
        $path = StoreCourseProfile::path($course->slug);

        if (! $disk->exists($path)) {
            return null;
        }

        /** @var array{points: list<array{d: float|int, lat: float|int, lon: float|int}>} $profile */
        $profile = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $points = $profile['points'];

        $elements = Http::asForm()
            ->timeout(60)
            ->withUserAgent('VaudRide/1.0 (digital@vaud-promotion.ch)')
            ->post((string) config('services.overpass.url'), ['data' => $this->query($points)])
            ->throw()
            ->json('elements', []);

        $known = $course->pois()->pluck('osm_id')->filter()->all();
        $created = 0;

        foreach (array_slice($elements, 0, self::MAX_PER_COURSE * 2) as $element) {
            $osmId = ($element['type'] ?? '').'/'.($element['id'] ?? '');
            $tags = $element['tags'] ?? [];
            $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $lon = $element['lon'] ?? $element['center']['lon'] ?? null;
            $category = PoiCategory::fromOsmTags($tags);

            if ($category === null || $lat === null || $lon === null || in_array($osmId, $known, true)) {
                continue;
            }

            [$distance, $offset] = $this->alongCourse($points, (float) $lat, (float) $lon);

            if ($offset > self::CORRIDOR_M + 30 || $created >= self::MAX_PER_COURSE) {
                continue;
            }

            $course->pois()->create([
                'title' => $this->title($tags, $category),
                'distance_m' => (int) round($distance),
                'lat' => $lat,
                'lon' => $lon,
                'url' => $this->url($tags),
                'category' => $category,
                'source' => 'osm',
                'osm_id' => $osmId,
                'published' => false,
            ]);
            $known[] = $osmId;
            $created++;
        }

        return $created;
    }

    /**
     * @param  list<array{lat: float|int, lon: float|int}>  $points
     */
    private function query(array $points): string
    {
        // the profile is sampled every 5 m: a point every 250 m is plenty for a 120 m corridor
        $every = max(1, intdiv(self::ROUTE_STEP_M, 5));
        $line = [];

        foreach ($points as $i => $p) {
            if ($i % $every === 0 || $i === array_key_last($points)) {
                $line[] = round($p['lat'], 5).','.round($p['lon'], 5);
            }
        }

        $around = 'around:'.self::CORRIDOR_M.','.implode(',', $line);
        $filters = [
            '["tourism"~"^(viewpoint|attraction|artwork|museum|gallery)$"]["name"]',
            '["historic"~"^(castle|monument|ruins|archaeological_site|fort|manor|church|chapel)$"]["name"]',
            '["natural"~"^(peak|spring|waterfall|cave_entrance)$"]["name"]',
            '["man_made"~"^(tower|lighthouse|observation)$"]["name"]',
            '["amenity"="drinking_water"]',
            '["craft"="winery"]["name"]',
            '["shop"~"^(wine|farm|cheese)$"]["name"]',
            '["amenity"~"^(cafe|restaurant)$"]["name"]',
        ];
        $statements = implode('', array_map(fn (string $f): string => "nwr({$around}){$f};", $filters));

        return "[out:json][timeout:50];({$statements});out center tags;";
    }

    /**
     * Distance along the course of the nearest route point, and how far off the road the place is (m).
     *
     * @param  list<array{d: float|int, lat: float|int, lon: float|int}>  $points
     * @return array{float, float}
     */
    private function alongCourse(array $points, float $lat, float $lon): array
    {
        $cos = cos(deg2rad($lat));
        $best = [0.0, INF];

        foreach ($points as $p) {
            $dy = ($p['lat'] - $lat) * 111_320;
            $dx = ($p['lon'] - $lon) * 111_320 * $cos;
            $meters = sqrt($dx * $dx + $dy * $dy);

            if ($meters < $best[1]) {
                $best = [(float) $p['d'], $meters];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, string>  $tags
     * @return array{fr: string, de: string, en: string}
     */
    private function title(array $tags, PoiCategory $category): array
    {
        $name = $tags['name'] ?? null;

        if ($name === null) {
            return ['fr' => 'Fontaine', 'de' => 'Trinkwasser', 'en' => 'Drinking water'];
        }

        return [
            'fr' => $tags['name:fr'] ?? $name,
            'de' => $tags['name:de'] ?? $name,
            'en' => $tags['name:en'] ?? $name,
        ];
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function url(array $tags): ?string
    {
        $site = $tags['website'] ?? $tags['contact:website'] ?? null;

        if ($site !== null && preg_match('#^https?://#i', $site) === 1) {
            return $site;
        }

        if (isset($tags['wikipedia']) && preg_match('/^([a-z]{2,3}):(.+)$/', $tags['wikipedia'], $m) === 1) {
            return "https://{$m[1]}.wikipedia.org/wiki/".rawurlencode(str_replace(' ', '_', $m[2]));
        }

        return null;
    }
}
