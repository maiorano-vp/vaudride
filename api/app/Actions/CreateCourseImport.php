<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ImportSource;
use App\Enums\LicenceStatus;
use App\Exceptions\InvalidGpxException;
use App\Models\Course;
use App\Models\CourseImport;
use App\Models\User;
use App\Services\GpxRoute;
use Illuminate\Support\Str;

final readonly class CreateCourseImport
{
    /**
     * A slug from the title that no import or course uses yet (-2, -3… when taken).
     */
    public static function slugFor(string $title): string
    {
        $base = Str::slug($title) ?: 'parcours';
        $slug = $base;

        for ($i = 2; CourseImport::query()->where('course_slug', $slug)->exists() || Course::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * Stores a submitted GPX with its geometry summary and altitude preview; rights start as unknown.
     *
     * @throws InvalidGpxException
     */
    public function handle(User $user, string $gpx, ImportSource $source, ?string $sourceUrl, ?string $title, ?string $author): CourseImport
    {
        $route = GpxRoute::parse($gpx);
        $title ??= $route->name ?? 'Untitled route';

        return $user->courseImports()->create([
            'source' => $source,
            'source_url' => $sourceUrl,
            'title' => $title,
            'course_slug' => self::slugFor($title),
            'author' => $author,
            'licence_status' => LicenceStatus::Unknown,
            'reversed' => false,
            'gpx' => $gpx,
            'summary' => $route->summary(),
            'preview' => $route->preview(),
        ]);
    }
}
