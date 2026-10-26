<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ride;
use App\Models\Segment;

/**
 * Times a ride on the course's published segments from its samples. The samples come from the player's
 * browser, so an effort is only kept when it is physically plausible; it is still not a verified result.
 */
final readonly class ComputeSegmentEfforts
{
    private const float MAX_SPEED_MS = 30; // 108 km/h: faster than any real descent on these roads

    private const int MAX_GAP_S = 10; // no sample for longer than this around a line: the crossing time is a guess

    public function handle(Ride $ride): void
    {
        /** @var list<array{t: int|float, d: int|float, p: int|float}> $samples */
        $samples = $ride->samples;

        if (count($samples) < 2) {
            return;
        }

        foreach ($ride->course->segments()->where('published', true)->get() as $segment) {
            $effort = $this->effort($samples, $segment);

            if ($effort === null) {
                continue;
            }

            $ride->efforts()->create([
                'segment_id' => $segment->id,
                'user_id' => $ride->user_id,
                'time_s' => round($effort['time_s'], 2),
                'started_at' => $ride->started_at->copy()->addSeconds((int) round($effort['started_at_s'])),
                'avg_power_w' => $effort['avg_power_w'],
            ]);
        }
    }

    /**
     * @param  list<array{t: int|float, d: int|float, p: int|float}>  $samples
     * @return array{time_s: float, started_at_s: float, avg_power_w: int|null}|null
     */
    private function effort(array $samples, Segment $segment): ?array
    {
        $start = $this->timeAt($samples, $segment->start_m);
        $end = $this->timeAt($samples, $segment->end_m);

        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        $powers = [];

        foreach ($samples as $i => $sample) {
            if ($sample['d'] >= $segment->start_m && $sample['d'] <= $segment->end_m) {
                $powers[] = $sample['p'];
            }

            $next = $samples[$i + 1] ?? null;

            if ($next === null || $next['d'] <= $segment->start_m || $sample['d'] >= $segment->end_m) {
                continue;
            }

            $seconds = $next['t'] - $sample['t'];
            $metres = $next['d'] - $sample['d'];

            if ($seconds <= 0 || $metres < 0 || $metres / $seconds > self::MAX_SPEED_MS) {
                return null;
            }
        }

        return [
            'time_s' => $end - $start,
            'started_at_s' => $start,
            'avg_power_w' => $powers === [] ? null : (int) round(array_sum($powers) / count($powers)),
        ];
    }

    /**
     * The ride clock when the rider crossed `$distance`, interpolated between the two samples around it.
     * Null when the ride did not reach it, started past it, or has no sample close enough to trust.
     *
     * @param  list<array{t: int|float, d: int|float}>  $samples
     */
    private function timeAt(array $samples, int $distance): ?float
    {
        if ($samples[0]['d'] > $distance) {
            return null;
        }

        foreach ($samples as $i => $sample) {
            if ($sample['d'] < $distance) {
                continue;
            }

            if ($i === 0) {
                return (float) $sample['t'];
            }

            $before = $samples[$i - 1];

            if ($sample['t'] - $before['t'] > self::MAX_GAP_S) {
                return null;
            }

            $span = $sample['d'] - $before['d'];

            return $span <= 0 ? (float) $sample['t'] : $before['t'] + ($sample['t'] - $before['t']) * ($distance - $before['d']) / $span;
        }

        return null;
    }
}
