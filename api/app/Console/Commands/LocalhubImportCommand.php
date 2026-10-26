<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportLocalhubPois;
use App\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:import {slug : Slug of the course} {--dry-run : Only list what would be imported}')]
#[Description('Imports the Local Hub places near a course as unpublished points of interest')]
final class LocalhubImportCommand extends Command
{
    public function handle(ImportLocalhubPois $import): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $course = Course::query()->where('slug', $this->argument('slug'))->first();

        // a dry run reads the course profile only: it needs no database row
        if (! $course instanceof Course && $dryRun) {
            $course = new Course(['slug' => $this->argument('slug')]);
        }

        if (! $course instanceof Course) {
            $this->error('No course with that slug.');

            return self::FAILURE;
        }

        $result = $import->handle($course, $dryRun);

        if ($result === null) {
            $this->error('Nothing to import: the course has no profile yet, or the catalogue was never synced (php artisan localhub:sync).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->table(['Lieu', 'Catégorie', 'Distance (m)'], array_map(fn (array $p): array => [$p['title'], $p['category'], $p['distance_m']], $result['preview']));
            $this->info("{$result['created']} would be imported, {$result['skipped']} skipped (out of reach, no coordinates). Nothing was written.");

            return self::SUCCESS;
        }

        $this->info("{$result['created']} imported, {$result['skipped']} skipped (out of reach, no coordinates or already imported).");

        return self::SUCCESS;
    }
}
