<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ExportCourseImport;
use App\Exceptions\InvalidGpxException;
use App\Models\CourseImport;
use DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('course:export {import : Course import id} {--force : Export even though rights are unknown}')]
#[Description('Writes a reviewed import to courses/<slug>/ so the pipeline can build it')]
final class ExportCourseImportCommand extends Command
{
    public function handle(ExportCourseImport $exportCourseImport): int
    {
        $import = CourseImport::query()->find($this->argument('import'));

        if (! $import instanceof CourseImport) {
            $this->error('Unknown import.');

            return self::FAILURE;
        }

        try {
            $directory = $exportCourseImport->handle($import, config()->string('courses.source_path'), (bool) $this->option('force'));
        } catch (DomainException|InvalidGpxException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Exported to '.$directory.' — now run: pipeline/build_course.py '.$import->course_slug);

        return self::SUCCESS;
    }
}
