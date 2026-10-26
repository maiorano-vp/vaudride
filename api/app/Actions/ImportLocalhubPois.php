<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PoiCategory;
use App\Models\Course;
use App\Models\LocalhubPlace;
use Illuminate\Support\Facades\Storage;

/**
 * Proposes the Local Hub places near a course as unpublished points of interest, for an admin to review.
 * Reads the local copy of the catalogue (see SyncLocalhubPlaces), so it is instant.
 * Places along the road count within 300 m; lodging counts around the start and the finish (3 km).
 */
final readonly class ImportLocalhubPois
{
    private const int ALONG_ROUTE_M = 300;

    private const int LODGING_AROUND_ENDS_M = 3000;

    /**
     * @return array{created: int, skipped: int, preview: list<array{title: string, category: string, distance_m: int}>}|null
     *                                                                                                                        null when the course has no profile yet or the catalogue was never synced
     */
    public function handle(Course $course, bool $dryRun = false, bool $publish = false): ?array
    {
        $disk = Storage::disk(StoreCourseProfile::DISK);
        $path = StoreCourseProfile::path($course->slug);

        if (! $disk->exists($path) || ! LocalhubPlace::query()->exists()) {
            return null;
        }

        /** @var array{points: list<array{d: float|int, lat: float|int, lon: float|int}>} $profile */
        $profile = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $points = $profile['points'];
        $first = $points[0];
        $last = $points[array_key_last($points)];
        $known = $dryRun ? [] : $course->pois()->pluck('external_id')->filter()->all();
        $created = 0;
        $skipped = 0;
        $preview = [];

        // a box around the route (with the lodging margin) keeps the database from handing over the whole catalogue
        $margin = 0.04; // about 4.4 km
        $lats = array_column($points, 'lat');
        $lons = array_column($points, 'lon');
        $candidates = LocalhubPlace::query()
            ->whereBetween('lat', [min($lats) - $margin, max($lats) + $margin])
            ->whereBetween('lon', [min($lons) - $margin, max($lons) + $margin])
            ->get();

        foreach ($candidates as $place) {
            if (in_array($place->id, $known, true)) {
                $skipped++;

                continue;
            }

            [$distance, $offset] = $this->alongCourse($points, $place->lat, $place->lon);
            $nearEnds = min(
                $this->metres($first['lat'], $first['lon'], $place->lat, $place->lon),
                $this->metres($last['lat'], $last['lon'], $place->lat, $place->lon),
            );
            $relevant = $place->category === PoiCategory::Lodging ? $nearEnds <= self::LODGING_AROUND_ENDS_M : $offset <= self::ALONG_ROUTE_M;

            if (! $relevant) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $preview[] = ['title' => $place->title['fr'], 'category' => $place->category->value, 'distance_m' => (int) round($distance)];
                $created++;

                continue;
            }

            $course->pois()->create([
                'title' => $place->title,
                'description' => $place->summary,
                'distance_m' => (int) round($distance),
                'lat' => $place->lat,
                'lon' => $place->lon,
                'url' => $place->url,
                'address' => $place->address,
                'category' => $place->category,
                'source' => 'localhub',
                'external_id' => $place->id,
                'published' => $publish,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'preview' => $preview];
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
