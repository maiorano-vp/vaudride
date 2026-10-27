<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The player's own finished rides on a course, to ride against: their fastest and their latest.
 * Only time and distance are sent; power and heart rate stay out of the response.
 */
final readonly class CourseGhostsController
{
    /** A ride that stops within this distance of the end counts as finished (the last sample is rarely on the line). */
    private const int FINISH_TOLERANCE_M = 20;

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $course = Course::query()->where('slug', $slug)->firstOrFail();

        $finished = fn () => $user->rides()
            ->where('course_id', $course->id)
            ->where('distance_m', '>=', $course->distance_m - self::FINISH_TOLERANCE_M);

        return response()->json([
            'data' => [
                'best' => $this->ghost($finished()->orderBy('duration_s')->first()),
                'last' => $this->ghost($finished()->latest('started_at')->first()),
            ],
        ]);
    }

    /**
     * @return array{started_at: string, duration_s: int, samples: list<array{0: int, 1: float}>}|null
     */
    private function ghost(?Ride $ride): ?array
    {
        if ($ride === null) {
            return null;
        }

        return [
            'started_at' => $ride->started_at->toIso8601String(),
            'duration_s' => $ride->duration_s,
            'samples' => array_map(fn (array $sample): array => [$sample['t'], $sample['d']], $ride->samples),
        ];
    }
}
