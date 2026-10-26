<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\LocalhubPlace;
use App\Services\LocalhubClient;
use App\Services\LocalhubPlaceMapper;

/** Refreshes the local copy of the Local Hub catalogue. Reading all of it takes minutes: run it from the command line. */
final readonly class SyncLocalhubPlaces
{
    private const int CHUNK = 200;

    public function __construct(private LocalhubClient $localhub, private LocalhubPlaceMapper $mapper) {}

    /**
     * @return array{synced: int, without_position: int, removed: int}
     */
    public function handle(): array
    {
        $startedAt = now();
        $rows = [];
        $synced = 0;
        $without = 0;

        foreach ($this->localhub->pois() as $hit) {
            $place = $this->mapper->map($hit);

            if ($place === null) {
                $without++;

                continue;
            }

            $rows[] = [
                ...$place,
                'title' => json_encode($place['title'], JSON_UNESCAPED_UNICODE),
                'summary' => $place['summary'] === null ? null : json_encode($place['summary'], JSON_UNESCAPED_UNICODE),
                'synced_at' => $startedAt,
            ];

            if (count($rows) >= self::CHUNK) {
                $synced += $this->save($rows);
                $rows = [];
            }
        }

        $synced += $this->save($rows);

        // reached only when the whole catalogue was read: a failed pass keeps the previous copy untouched
        $removed = LocalhubPlace::query()->where('synced_at', '<', $startedAt)->delete();

        return ['synced' => $synced, 'without_position' => $without, 'removed' => $removed];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function save(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        LocalhubPlace::query()->upsert($rows, ['id'], ['title', 'summary', 'lat', 'lon', 'address', 'url', 'category', 'synced_at']);

        return count($rows);
    }
}
