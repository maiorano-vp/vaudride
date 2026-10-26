<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ImportSource;
use App\Enums\LicenceStatus;
use App\Exceptions\InvalidGpxException;
use App\Models\CourseImport;
use App\Models\User;
use App\Services\GpxRoute;

final readonly class CreateCourseImport
{
    /**
     * Stores a submitted GPX with its geometry summary and altitude preview; rights start as unknown.
     *
     * @throws InvalidGpxException
     */
    public function handle(User $user, string $gpx, ImportSource $source, ?string $sourceUrl, ?string $title, ?string $author): CourseImport
    {
        $route = GpxRoute::parse($gpx);

        return $user->courseImports()->create([
            'source' => $source,
            'source_url' => $sourceUrl,
            'title' => $title ?? $route->name ?? 'Untitled route',
            'author' => $author,
            'licence_status' => LicenceStatus::Unknown,
            'reversed' => false,
            'gpx' => $gpx,
            'summary' => $route->summary(),
            'preview' => $route->preview(),
        ]);
    }
}
