<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LicenceStatus;
use App\Exceptions\InvalidGpxException;
use App\Models\CourseImport;
use App\Services\GpxRoute;
use DomainException;
use Illuminate\Support\Facades\File;

final readonly class ExportCourseImport
{
    /**
     * Writes courses/<slug>/{route.gpx, overrides.yaml, source.yaml} for the pipeline, applying trim and reversal.
     * Refuses routes whose rights are unknown unless forced; an existing overrides.yaml is never overwritten.
     *
     * @throws DomainException
     * @throws InvalidGpxException
     */
    public function handle(CourseImport $import, string $coursesPath, bool $force = false): string
    {
        $slug = $import->course_slug;
        if ($slug === null || $slug === '') {
            throw new DomainException('Set a course slug before exporting.');
        }
        if ($import->licence_status === LicenceStatus::Unknown && ! $force) {
            throw new DomainException('Rights over this route are unknown: clear them first (or use --force).');
        }

        $route = GpxRoute::parse($import->gpx)->trim($import->trim_start_m, $import->trim_end_m, $import->reversed);

        $directory = mb_rtrim($coursesPath, '/').'/'.$slug;
        File::ensureDirectoryExists($directory);

        File::put($directory.'/route.gpx', $route->toGpx($import->title, 'Imported route; altitudes are re-sampled by the pipeline.'));

        if (! File::exists($directory.'/overrides.yaml')) {
            File::put($directory.'/overrides.yaml', "name: {$this->scalar($import->title)}\nversion: 1\noverrides: []\n");
        }

        File::put($directory.'/source.yaml', implode("\n", [
            'source: '.$this->scalar($import->source->value),
            'source_url: '.$this->scalar($import->source_url),
            'author: '.$this->scalar($import->author),
            'licence_status: '.$this->scalar($import->licence_status->value),
            'trim_start_m: '.$this->scalar($import->trim_start_m),
            'trim_end_m: '.$this->scalar($import->trim_end_m),
            'reversed: '.($import->reversed ? 'true' : 'false'),
            'imported_at: '.$this->scalar($import->created_at->toIso8601String()),
            '',
        ]));

        $import->update(['exported_at' => now()]);

        return $directory;
    }

    /**
     * JSON scalars are valid YAML, which keeps quoting correct without a YAML dependency.
     */
    private function scalar(string|float|null $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
