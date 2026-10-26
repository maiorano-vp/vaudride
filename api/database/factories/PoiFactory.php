<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\Poi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poi>
 */
final class PoiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(2, true);

        return [
            'course_id' => Course::factory(),
            'distance_m' => fake()->numberBetween(0, 10_000),
            'title' => ['fr' => $title, 'de' => $title, 'en' => $title],
            'description' => null,
            'lat' => fake()->latitude(46.4, 46.6),
            'lon' => fake()->longitude(6.2, 6.4),
            'url' => null,
        ];
    }
}
