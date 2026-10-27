<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseForecast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseForecast>
 */
final class CourseForecastFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'day' => today(),
            'temp_min' => fake()->randomFloat(1, 2, 12),
            'temp_max' => fake()->randomFloat(1, 12, 28),
            'precipitation_mm' => fake()->randomFloat(1, 0, 15),
            'pictogram' => fake()->numberBetween(1, 35),
            'place' => 'Lutry',
            'fetched_at' => now(),
        ];
    }
}
