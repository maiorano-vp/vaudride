<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Segment>
 */
final class SegmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Montée '.fake()->unique()->numberBetween(1, 9999);
        $start = fake()->numberBetween(0, 5_000);

        return [
            'course_id' => Course::factory(),
            'name' => ['fr' => $name, 'de' => $name, 'en' => $name],
            'start_m' => $start,
            'end_m' => $start + fake()->numberBetween(500, 3_000),
            'category' => null,
            'published' => true,
        ];
    }
}
