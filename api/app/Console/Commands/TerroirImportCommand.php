<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportTerroirPois;
use App\Models\Course;
use App\Services\TerroirClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('terroir:import {slug? : Slug of the course; every course when omitted} {--dry-run : Only list what would be imported} {--publish : Publish the places at once instead of leaving them to review}')]
#[Description('Imports the Terroir partners (agritourism, bakeries, grocers) near a course as points of interest, unpublished unless --publish')]
final class TerroirImportCommand extends Command
{
    public function handle(ImportTerroirPois $import, TerroirClient $client): int
    {
        if (! $client->configured()) {
            $this->error('TERROIR_TOKEN is not set.');

            return self::FAILURE;
        }

        $slug = $this->argument('slug');
        $dryRun = (bool) $this->option('dry-run');
        $courses = $slug === null
            ? Course::query()->orderBy('slug')->get()
            : Course::query()->where('slug', $slug)->get();

        // a dry run reads the course profile only: it needs no database row
        if ($courses->isEmpty() && $slug !== null && $dryRun) {
            $courses = collect([new Course(['slug' => $slug])]);
        }

        if ($courses->isEmpty()) {
            $this->error('No course with that slug.');

            return self::FAILURE;
        }

        $places = $import->partners();
        $this->info(count($places).' partners read.');
        $total = 0;

        foreach ($courses as $course) {
            $result = $import->handle($course, $places, $dryRun, (bool) $this->option('publish'));

            if ($result === null) {
                $this->warn("{$course->slug}: no profile yet");

                continue;
            }

            $total += $result['created'];
            $this->line("{$course->slug}: {$result['created']} ".($dryRun ? 'would be imported' : 'imported').", {$result['skipped']} already there");

            if ($dryRun && $slug !== null) {
                $this->table(['Lieu', 'Catégorie', 'Distance (m)'], array_map(fn (array $p): array => [$p['title'], $p['category'], $p['distance_m']], $result['preview']));
            }
        }

        $this->info($total.($dryRun ? ' would be imported. Nothing was written.' : ' imported'.($this->option('publish') ? ' and published.' : ', to review and publish in the admin.')));

        return self::SUCCESS;
    }
}
