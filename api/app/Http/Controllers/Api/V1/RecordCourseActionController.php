<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordCourseAction;
use App\Enums\CourseActionType;
use App\Http\Requests\Api\V1\RecordCourseActionRequest;
use App\Models\Course;
use Illuminate\Http\Response;

/** Open to everybody and anonymous: counts a step towards riding the course for real (a GPX taken, a transit link opened…). */
final readonly class RecordCourseActionController
{
    public function __invoke(RecordCourseActionRequest $request, RecordCourseAction $record, string $slug): Response
    {
        $course = Course::query()->where('slug', $slug)->where('published', true)->firstOrFail();

        $record->handle($course, CourseActionType::from($request->string('action')->toString()));

        return response()->noContent();
    }
}
