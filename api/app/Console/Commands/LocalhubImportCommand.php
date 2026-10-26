<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportLocalhubPois;
use App\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:import {slug : Slug of the course}')]
#[Description('Imports the Local Hub places near a course as unpublished points of interest')]
final class LocalhubImportCommand extends Command
{
    public function handle(ImportLocalhubPois $import): int
    {
        $course = Course::query()->where('slug', $this->argument('slug'))->first();

        if (! $course instanceof Course) {
            $this->error('No course with that slug.');

            return self::FAILURE;
        }

        $result = $import->handle($course);

        if ($result === null) {
            $this->error('This course has no profile yet.');

            return self::FAILURE;
        }

        $this->info("{$result['created']} imported, {$result['skipped']} skipped (out of reach, no coordinates or already imported).");

        return self::SUCCESS;
    }
}
