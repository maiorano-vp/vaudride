<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StravaConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StravaConnection>
 */
final class StravaConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'athlete_id' => fake()->numberBetween(1_000, 9_999_999),
            'access_token' => 'access-'.fake()->uuid(),
            'refresh_token' => 'refresh-'.fake()->uuid(),
            'expires_at' => now()->addHours(6),
            'scope' => 'read,activity:write',
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subMinute()]);
    }
}
