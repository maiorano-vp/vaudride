<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Reads the Terroir API (https://ds-edirekt.myvaud.dev/docs/api), the partners of Vaud Promotion, with a bearer token. */
final readonly class TerroirClient
{
    private const int PER_PAGE = 1000;

    private const int MAX_PAGES = 20;

    public function configured(): bool
    {
        return (string) config('services.terroir.token') !== '';
    }

    /**
     * The partner groups (types): id, slug, title.
     *
     * @return list<array{id: int, slug: string, title: string|null}>
     */
    public function groups(): array
    {
        return $this->request()->get('/v1/partners/groups', ['per_page' => 500])->throw()->json('data', []);
    }

    /**
     * The partners of one group, page after page. No canton filter: the API writes "Vaud" in full and the corridor of a course does the sorting.
     *
     * @return iterable<array<string, mixed>>
     */
    public function partners(int $groupId): iterable
    {
        $page = 1;

        do {
            $response = $this->request()->get('/v1/partners', [
                'group' => $groupId,
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ])->throw();

            foreach ($response->json('data', []) as $partner) {
                yield $partner;
            }

            $last = (int) $response->json('meta.last_page', 1);
            $page++;
        } while ($page <= min($last, self::MAX_PAGES));
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('TERROIR_TOKEN is not set.');
        }

        return Http::baseUrl((string) config('services.terroir.url'))
            ->withToken((string) config('services.terroir.token'))
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 500, throw: false);
    }
}
