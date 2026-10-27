<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PoiCategory;
use App\Models\Course;
use App\Models\LocalhubPlace;
use App\Services\CourseCorridor;

/**
 * Adds the Local Hub places near a course as points of interest. They are the destination's own data, so they are
 * published at once; an admin can hide any of them afterwards, or ask for drafts.
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
    public function handle(Course $course, bool $dryRun = false, bool $publish = true): ?array
    {
        $corridor = CourseCorridor::forCourse($course);

        if (! $corridor instanceof CourseCorridor || ! LocalhubPlace::query()->exists()) {
            return null;
        }

        $known = $dryRun ? [] : $course->pois()->pluck('external_id')->filter()->all();
        $created = 0;
        $skipped = 0;
        $preview = [];

        // a box around the route (with the lodging margin) keeps the database from handing over the whole catalogue
        $box = $corridor->box(0.04);
        $candidates = LocalhubPlace::query()
            ->whereBetween('lat', $box['lat'])
            ->whereBetween('lon', $box['lon'])
            ->get();

        foreach ($candidates as $place) {
            if (in_array($place->id, $known, true)) {
                $skipped++;

                continue;
            }

            [$distance, $offset] = $corridor->along($place->lat, $place->lon);
            $relevant = $place->category === PoiCategory::Lodging
                ? $corridor->nearEnds($place->lat, $place->lon) <= self::LODGING_AROUND_ENDS_M
                : $offset <= self::ALONG_ROUTE_M;

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
                'image_url' => $place->image_url,
                'image_credit' => $place->image_credit,
                'category' => $place->category,
                'source' => 'localhub',
                'external_id' => $place->id,
                'published' => $publish,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'preview' => $preview];
    }
}
