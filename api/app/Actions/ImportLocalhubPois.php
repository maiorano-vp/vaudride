<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PoiCategory;
use App\Models\Course;
use App\Services\LocalhubClient;
use Illuminate\Support\Facades\Storage;

/**
 * Proposes the Local Hub places near a course as unpublished points of interest, for an admin to review.
 * Places along the road count within 300 m; lodging counts around the start and the finish (3 km).
 */
final readonly class ImportLocalhubPois
{
    private const int ALONG_ROUTE_M = 300;

    private const int LODGING_AROUND_ENDS_M = 3000;

    private const array KEYWORDS = [
        'lodging' => ['hébergement', 'hebergement', 'hôtel', 'hotel', 'camping', 'gîte', 'gite', 'chambre', 'auberge', 'hostel', 'accommodation', 'lodging', 'b&b'],
        'wine' => ['vin', 'cave', 'wine', 'vigneron', 'vignoble'],
        'food' => ['restaur', 'gastro', 'café', 'cafe', 'boulang', 'fromag', 'terroir', 'producteur', 'food'],
        'bike' => ['vélo', 'velo', 'bike', 'cycle'],
        'station' => ['gare', 'train', 'station'],
        'water' => ['fontaine', 'eau potable'],
        'viewpoint' => ['panorama', 'point de vue', 'belvédère', 'belvedere', 'viewpoint'],
        'history' => ['château', 'chateau', 'patrimoine', 'histoire', 'monument', 'église', 'eglise', 'castle'],
        'culture' => ['musée', 'musee', 'culture', 'exposition', 'museum', 'art'],
        'nature' => ['nature', 'parc', 'lac', 'randonn', 'cascade', 'grotte', 'sommet'],
    ];

    public function __construct(private LocalhubClient $localhub) {}

    /**
     * @return array{created: int, skipped: int}|null null when the course has no profile yet
     */
    public function handle(Course $course): ?array
    {
        $disk = Storage::disk(StoreCourseProfile::DISK);
        $path = StoreCourseProfile::path($course->slug);

        if (! $disk->exists($path)) {
            return null;
        }

        /** @var array{points: list<array{d: float|int, lat: float|int, lon: float|int}>} $profile */
        $profile = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $points = $profile['points'];
        $first = $points[0];
        $last = $points[array_key_last($points)];
        $known = $course->pois()->pluck('external_id')->filter()->all();
        $created = 0;
        $skipped = 0;

        // a full pass over the catalogue can take a while: the command line has no time limit, the admin button gets two minutes
        set_time_limit(120);

        foreach ($this->localhub->pois() as $hit) {
            $id = isset($hit['id']) ? 'localhub:'.$hit['id'] : null;
            $coords = $this->coordinates($hit);

            if ($id === null || $coords === null || in_array($id, $known, true)) {
                $skipped++;

                continue;
            }

            $category = $this->category($hit);
            [$distance, $offset] = $this->alongCourse($points, $coords['lat'], $coords['lon']);
            $nearEnds = min($this->metres($first['lat'], $first['lon'], $coords['lat'], $coords['lon']), $this->metres($last['lat'], $last['lon'], $coords['lat'], $coords['lon']));
            $relevant = $category === PoiCategory::Lodging ? $nearEnds <= self::LODGING_AROUND_ENDS_M : $offset <= self::ALONG_ROUTE_M;

            if (! $relevant) {
                $skipped++;

                continue;
            }

            $course->pois()->create([
                'title' => $this->localized($hit['name'] ?? null) ?? ['fr' => '?'],
                'description' => $this->localized($hit['summary'] ?? null),
                'distance_m' => (int) round($distance),
                'lat' => $coords['lat'],
                'lon' => $coords['lon'],
                'url' => $this->url($hit),
                'address' => $this->address($hit),
                'category' => $category,
                'source' => 'localhub',
                'external_id' => $id,
                'published' => false,
            ]);
            $known[] = $id;
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array{lat: float, lon: float}|null
     */
    private function coordinates(array $hit): ?array
    {
        foreach ([$hit['location'] ?? [], $hit['_geo'] ?? [], $hit] as $source) {
            $lat = $source['lat'] ?? $source['latitude'] ?? null;
            $lon = $source['lng'] ?? $source['lon'] ?? $source['longitude'] ?? null;

            if (is_numeric($lat) && is_numeric($lon)) {
                return ['lat' => (float) $lat, 'lon' => (float) $lon];
            }
        }

        return null;
    }

    /**
     * The category from the place's Local Hub categories, by keyword; culture when nothing matches.
     *
     * @param  array<string, mixed>  $hit
     */
    private function category(array $hit): PoiCategory
    {
        $text = mb_strtolower(implode(' ', array_filter([
            $this->text($hit['rootAssetCategory'] ?? null),
            $this->text($hit['assetCategory'] ?? null),
        ])));

        foreach (self::KEYWORDS as $category => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return PoiCategory::from($category);
                }
            }
        }

        return PoiCategory::Culture;
    }

    private function text(mixed $category): string
    {
        if (! is_array($category)) {
            return is_string($category) ? $category : '';
        }

        return mb_trim(($category['slug'] ?? '').' '.(is_array($category['name'] ?? null) ? implode(' ', $category['name']) : ($category['name'] ?? '')));
    }

    /**
     * A value that is either one string or a map by language, as {fr, de, en}.
     *
     * @return array{fr: string, de?: string, en?: string}|null
     */
    private function localized(mixed $value): ?array
    {
        if (is_string($value) && $value !== '') {
            return ['fr' => $value, 'de' => $value, 'en' => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $fr = $value['fr'] ?? $value['fr_CH'] ?? null;
        $fr = is_string($fr) && $fr !== '' ? $fr : (is_string(reset($value)) ? (string) reset($value) : null);

        if ($fr === null) {
            return null;
        }

        return [
            'fr' => $fr,
            'de' => is_string($value['de'] ?? null) && $value['de'] !== '' ? $value['de'] : $fr,
            'en' => is_string($value['en'] ?? null) && $value['en'] !== '' ? $value['en'] : $fr,
        ];
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function url(array $hit): ?string
    {
        $candidates = [];

        foreach ($hit['links'] ?? [] as $link) {
            $candidates[] = $link['url'] ?? null;
        }

        $candidates[] = $hit['location']['website'] ?? null;

        foreach ($candidates as $url) {
            if (is_string($url) && preg_match('#^https?://#i', $url) === 1) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function address(array $hit): ?string
    {
        $location = $hit['location'] ?? null;

        if (! is_array($location)) {
            return null;
        }

        $line = mb_trim(implode(', ', array_filter([
            $location['address'] ?? null,
            mb_trim(($location['postcode'] ?? '').' '.($location['city'] ?? '')),
        ])));

        return $line === '' ? null : $line;
    }

    /**
     * Distance along the course of the nearest route point, and how far off the road the place is (m).
     *
     * @param  list<array{d: float|int, lat: float|int, lon: float|int}>  $points
     * @return array{float, float}
     */
    private function alongCourse(array $points, float $lat, float $lon): array
    {
        $best = [0.0, INF];

        foreach ($points as $p) {
            $meters = $this->metres($p['lat'], $p['lon'], $lat, $lon);

            if ($meters < $best[1]) {
                $best = [(float) $p['d'], $meters];
            }
        }

        return $best;
    }

    private function metres(float|int $lat1, float|int $lon1, float $lat2, float $lon2): float
    {
        $dy = ($lat2 - $lat1) * 111_320;
        $dx = ($lon2 - $lon1) * 111_320 * cos(deg2rad($lat2));

        return sqrt($dx * $dx + $dy * $dy);
    }
}
