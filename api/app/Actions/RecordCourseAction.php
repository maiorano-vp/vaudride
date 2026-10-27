<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CourseActionType;
use App\Models\Course;
use App\Models\CourseAction;
use Illuminate\Support\Str;

/** Counts one step towards riding a course for real, on today's (Swiss) day. Nothing about who took it. */
final readonly class RecordCourseAction
{
    public function handle(Course $course, CourseActionType $action): void
    {
        $day = now('Europe/Zurich')->toDateString();

        // the row for the day is created once, then incremented in the database: two visitors at once both count
        CourseAction::query()->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'course_id' => $course->id,
            'action' => $action->value,
            'day' => $day,
            'count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CourseAction::query()
            ->where('course_id', $course->id)
            ->where('action', $action->value)
            ->where('day', $day)
            ->increment('count');
    }
}
