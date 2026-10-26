<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\DetectCourseSegments;
use App\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('segments:detect {slug? : Only this course (every course by default)} {--draft : Add the segments unpublished, to review them first}')]
#[Description('Adds the climbs of every course as segments (published unless --draft)')]
final class DetectSegmentsCommand extends Command
{
    public function handle(DetectCourseSegments $detect): int
    {
        $courses = Course::query()->when($this->argument('slug'), fn ($query, string $slug) => $query->where('slug', $slug))->orderBy('slug')->get();

        if ($courses->isEmpty()) {
            $this->error('No course found.');

            return self::FAILURE;
        }

        foreach ($courses as $course) {
            $created = $detect->handle($course, ! $this->option('draft'));

            $this->line($created === null
                ? "{$course->slug}: no profile yet, skipped"
                : "{$course->slug}: {$created} segment(s) added");
        }

        return self::SUCCESS;
    }
}
