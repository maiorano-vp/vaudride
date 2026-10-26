<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Course;
use App\Models\Ride;
use App\Models\User;
use Carbon\CarbonInterface;

final readonly class RecordRide
{
    /**
     * Stores a ride; summary figures are derived from the samples, not trusted from the client.
     *
     * @param  non-empty-list<array{t: int, d: float|int, p: int, c?: int|null, h?: int|null, s: float|int}>  $samples
     */
    public function handle(User $user, Course $course, CarbonInterface $startedAt, int $elevationGainM, array $samples): Ride
    {
        $powers = array_column($samples, 'p');
        $heartRates = array_filter(array_column($samples, 'h'), fn (?int $bpm): bool => $bpm !== null && $bpm > 0);
        $last = $samples[array_key_last($samples)];

        return $user->rides()->create([
            'course_id' => $course->id,
            'started_at' => $startedAt,
            'duration_s' => $last['t'],
            'distance_m' => (int) round(min($last['d'], $course->distance_m)),
            'elevation_gain_m' => $elevationGainM,
            'avg_power_w' => (int) round(array_sum($powers) / count($powers)),
            'max_power_w' => max($powers),
            'avg_heart_rate' => $heartRates === [] ? null : (int) round(array_sum($heartRates) / count($heartRates)),
            'samples' => $samples,
        ]);
    }
}
