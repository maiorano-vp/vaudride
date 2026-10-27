<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Course;
use App\Services\CourseCorridor;
use App\Services\TerroirClient;
use App\Services\TerroirPartnerMapper;

/**
 * Adds the Terroir partners (agritourism, bakeries, grocers) found along a course as points of interest. They are
 * imported unpublished, to be read in the admin before riders see them (`$publish` to skip that). A place already
 * imported is never touched again, so what an admin edited stays. Partners count within 300 m of the road.
 */
final readonly class ImportTerroirPois
{
    private const int ALONG_ROUTE_M = 300;

    public function __construct(private TerroirClient $client, private TerroirPartnerMapper $mapper) {}

    /**
     * Every partner of the wanted groups, mapped; read once and handed to `handle()` for each course.
     *
     * @return list<array<string, mixed>>
     */
    public function partners(): array
    {
        $groups = collect($this->client->groups())->keyBy('slug');
        $places = [];

        foreach (TerroirPartnerMapper::CATEGORY_BY_GROUP as $slug => $category) {
            $id = $groups[$slug]['id'] ?? null;

            if ($id === null) {
                continue;
            }

            foreach ($this->client->partners((int) $id) as $partner) {
                $place = $this->mapper->map($partner, $category);

                if ($place !== null) {
                    $places[$place['id']] ??= $place;
                }
            }
        }

        return array_values($places);
    }

    /**
     * @param  list<array<string, mixed>>  $places  from `partners()`
     * @return array{created: int, skipped: int, preview: list<array{title: string, category: string, distance_m: int}>}|null
     *                                                                                                                        null when the course has no profile yet
     */
    public function handle(Course $course, array $places, bool $dryRun = false, bool $publish = false): ?array
    {
        $corridor = CourseCorridor::forCourse($course);

        if (! $corridor instanceof CourseCorridor) {
            return null;
        }

        $known = $dryRun ? [] : $course->pois()->pluck('external_id')->filter()->all();
        $created = 0;
        $skipped = 0;
        $preview = [];

        foreach ($places as $place) {
            [$distance, $offset] = $corridor->along($place['lat'], $place['lon']);

            if ($offset > self::ALONG_ROUTE_M) {
                continue;
            }

            if (in_array($place['id'], $known, true)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $preview[] = ['title' => $place['title']['fr'], 'category' => $place['category']->value, 'distance_m' => (int) round($distance)];
                $created++;

                continue;
            }

            $course->pois()->create([
                'title' => $place['title'],
                'description' => $place['description'],
                'distance_m' => (int) round($distance),
                'lat' => $place['lat'],
                'lon' => $place['lon'],
                'url' => $place['url'],
                'address' => $place['address'],
                'image_url' => $place['image_url'],
                'category' => $place['category'],
                'source' => 'terroir',
                'external_id' => $place['id'],
                'published' => $publish,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'preview' => $preview];
    }
}
