<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
final class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);
        $name = str($slug)->replace('-', ' ')->title()->toString();

        return [
            'slug' => $slug,
            'name' => ['fr' => $name, 'de' => $name, 'en' => $name],
            'version' => 1,
            'distance_m' => 10_000,
            'elevation_gain_m' => 750,
            'attribution' => '© swisstopo',
            'published' => true,
        ];
    }

    public function unpublished(): self
    {
        return $this->state(fn (array $attributes): array => ['published' => false]);
    }
}
