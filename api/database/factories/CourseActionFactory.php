<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourseActionType;
use App\Models\Course;
use App\Models\CourseAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseAction>
 */
final class CourseActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'action' => CourseActionType::GpxDownload,
            'day' => now()->toDateString(),
            'count' => fake()->numberBetween(1, 20),
        ];
    }
}
