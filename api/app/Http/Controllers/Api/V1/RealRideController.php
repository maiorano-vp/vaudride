<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordCourseAction;
use App\Enums\CourseActionType;
use App\Http\Requests\Api\V1\StoreRealRideRequest;
use App\Models\Course;
use App\Models\RealRide;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The player's passport: the courses they say they rode for real. Only their own are ever read or removed. */
final readonly class RealRideController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->user($request)->realRides()->with('course:id,slug,name')->orderByDesc('ridden_on')->get()
                ->map(fn (RealRide $ride): array => $this->present($ride))->all(),
        ]);
    }

    /** Declares a day on a course; declaring the same day again changes nothing. */
    public function store(StoreRealRideRequest $request, RecordCourseAction $record, string $slug): JsonResponse
    {
        $course = Course::query()->where('slug', $slug)->where('published', true)->firstOrFail();

        $ride = $this->user($request)->realRides()->firstOrCreate([
            'course_id' => $course->id,
            'ridden_on' => $request->string('ridden_on')->toString(),
        ]);

        if ($ride->wasRecentlyCreated) {
            $record->handle($course, CourseActionType::DeclaredReal); // the reports count the declaration, not the person
        }

        return response()->json(['data' => $this->present($ride->setRelation('course', $course))], $ride->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(Request $request, string $realRide): Response
    {
        $this->user($request)->realRides()->findOrFail($realRide)->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: string, ridden_on: string, course: array{slug: string, name: array<string, string>}}
     */
    private function present(RealRide $ride): array
    {
        return [
            'id' => $ride->id,
            'ridden_on' => $ride->ridden_on->toDateString(),
            'course' => ['slug' => $ride->course->slug, 'name' => $ride->course->name],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
