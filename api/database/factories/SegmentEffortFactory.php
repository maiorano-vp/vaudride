<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ride;
use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SegmentEffort>
 */
final class SegmentEffortFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'segment_id' => Segment::factory(),
            'ride_id' => Ride::factory(),
            'user_id' => User::factory(),
            'time_s' => fake()->randomFloat(2, 120, 1800),
            'started_at' => now(),
            'avg_power_w' => fake()->numberBetween(120, 320),
        ];
    }
}
