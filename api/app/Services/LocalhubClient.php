<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Reads the Local Hub API (https://app.localhub.ch/docs/api) with the project's bearer token. */
final readonly class LocalhubClient
{
    public const int PAGE_SIZE = 100;

    // only what the import reads: a full page is several MB otherwise
    private const string FIELDS = 'name,summary,location,links,attributes';

    private const int MAX_PAGES = 100;

    public function configured(): bool
    {
        return (string) config('services.localhub.token') !== '';
    }

    /**
     * Every point of interest, page after page.
     *
     * @return iterable<array<string, mixed>>
     */
    public function pois(): iterable
    {
        $page = 1;

        do {
            $data = $this->request()->get('/api/v1/pois', [
                'page' => $page,
                'pageSize' => self::PAGE_SIZE,
                'include' => 'location,links',
                'fields' => self::FIELDS,
            ])->throw()->json('data.pois', []);

            foreach ($data['hits'] ?? [] as $hit) {
                yield $hit;
            }

            $pages = (int) ($data['totalPages'] ?? 1);
            $page++;
        } while ($page <= min($pages, self::MAX_PAGES));
    }

    /**
     * The first point of interest, untouched: to see what the API really returns.
     *
     * @return array<string, mixed>|null
     */
    public function sample(): ?array
    {
        $data = $this->request()->get('/api/v1/pois', ['page' => 1, 'pageSize' => 1, 'include' => 'location,links', 'fields' => self::FIELDS])
            ->throw()->json('data.pois', []);

        return $data['hits'][0] ?? null;
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('LOCALHUB_TOKEN is not set.');
        }

        return Http::baseUrl((string) config('services.localhub.url'))
            ->withToken((string) config('services.localhub.token'))
            ->acceptJson()
            ->timeout(90)
            ->retry(2, 500, throw: false);
    }
}
