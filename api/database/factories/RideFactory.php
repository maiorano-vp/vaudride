<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ride>
 */
final class RideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'started_at' => now()->subHour(),
            'duration_s' => 3,
            'distance_m' => 30,
            'elevation_gain_m' => 2,
            'avg_power_w' => 200,
            'max_power_w' => 220,
            'avg_heart_rate' => 140,
            'samples' => [
                ['t' => 0, 'd' => 0, 'p' => 180, 'c' => 85, 'h' => 130, 's' => 20.0],
                ['t' => 1, 'd' => 6, 'p' => 200, 'c' => 86, 'h' => 140, 's' => 21.0],
                ['t' => 2, 'd' => 12, 'p' => 220, 'c' => 88, 'h' => 150, 's' => 22.0],
            ],
        ];
    }
}
