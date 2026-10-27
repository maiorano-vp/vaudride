<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\RealRide;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealRide>
 */
final class RealRideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'ridden_on' => now()->subDays(fake()->numberBetween(1, 90))->toDateString(),
        ];
    }
}
