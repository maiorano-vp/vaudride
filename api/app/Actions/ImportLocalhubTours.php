<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ImportSource;
use App\Exceptions\InvalidGpxException;
use App\Models\CourseImport;
use App\Models\User;
use App\Services\LocalhubClient;
use Illuminate\Http\Client\RequestException;

final readonly class ImportLocalhubTours
{
    public function __construct(private LocalhubClient $localhub, private CreateCourseImport $create) {}

    /**
     * Turns the published tours of a Local Hub category into GPX imports waiting for review (rights unknown).
     * A tour already imported (same GPX address) is skipped, so the command can be run again.
     *
     * @return array{created: int, skipped: int, failed: list<string>, preview: list<array{title: string, km: float|null, gain: int|null}>}
     */
    public function handle(User $user, string $categoryId, bool $dryRun): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'failed' => [], 'preview' => []];

        foreach ($this->localhub->tours($categoryId) as $tour) {
            $title = (string) ($tour['name']['fr'] ?? $tour['name']['en'] ?? '');
            $url = (string) ($tour['route'] ?? '');

            if ($url === '' || ! ($tour['is_published'] ?? false) || CourseImport::query()->where('source', ImportSource::Localhub)->where('source_url', $url)->exists()) {
                $result['skipped']++;

                continue;
            }

            $result['preview'][] = ['title' => $title, 'km' => $tour['gpx_total_distance'] ?? null, 'gain' => isset($tour['gpx_positive_elevation_gain']) ? (int) round($tour['gpx_positive_elevation_gain']) : null];

            if ($dryRun) {
                continue;
            }

            try {
                $this->create->handle($user, $this->localhub->gpx($url), ImportSource::Localhub, $url, $title !== '' ? $title : null, $tour['tenant']['name'] ?? null);
                $result['created']++;
            } catch (InvalidGpxException|RequestException $e) {
                $result['failed'][] = "{$title}: {$e->getMessage()}";
            }
        }

        return $result;
    }
}
