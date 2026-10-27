<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportLocalhubPois;
use App\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:import {slug? : Slug of the course; every course when omitted} {--dry-run : Only list what would be imported} {--draft : Import the places unpublished, to review them first}')]
#[Description('Imports the Local Hub places near a course as points of interest (published unless --draft)')]
final class LocalhubImportCommand extends Command
{
    public function handle(ImportLocalhubPois $import): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->argument('slug') === null) {
            return $this->importAll($import, $dryRun);
        }

        $course = Course::query()->where('slug', $this->argument('slug'))->first();

        // a dry run reads the course profile only: it needs no database row
        if (! $course instanceof Course && $dryRun) {
            $course = new Course(['slug' => $this->argument('slug')]);
        }

        if (! $course instanceof Course) {
            $this->error('No course with that slug.');

            return self::FAILURE;
        }

        $result = $import->handle($course, $dryRun, ! $this->option('draft'));

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

    /** Adds the places that are new to every course: the ones already imported are left untouched. */
    private function importAll(ImportLocalhubPois $import, bool $dryRun): int
    {
        $total = 0;

        foreach (Course::query()->orderBy('slug')->get() as $course) {
            $result = $import->handle($course, $dryRun, ! $this->option('draft'));

            if ($result === null) {
                $this->warn("{$course->slug}: no profile yet, or the catalogue was never synced");

                continue;
            }

            $total += $result['created'];
            $this->line("{$course->slug}: {$result['created']} ".($dryRun ? 'would be imported' : 'imported').", {$result['skipped']} skipped");
        }

        $this->info($total.($dryRun ? ' would be imported. Nothing was written.' : ' imported.'));

        return self::SUCCESS;
    }
}
