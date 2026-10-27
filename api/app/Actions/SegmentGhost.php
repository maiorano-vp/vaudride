<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Segment;
use App\Models\SegmentEffort;

/** Cuts the ride behind a segment effort down to the segment: [seconds since the start line, distance in m]. */
final readonly class SegmentGhost
{
    /**
     * @return list<array{0: float, 1: float}>
     */
    public function handle(Segment $segment, SegmentEffort $effort): array
    {
        /** @var list<array{t: int|float, d: int|float}> $samples */
        $samples = $effort->ride->samples;
        $startS = $this->timeAt($samples, $segment->start_m);
        $timeS = round($effort->time_s, 2);

        $points = [[0.0, (float) $segment->start_m]];

        if ($startS !== null) {
            foreach ($samples as $sample) {
                $t = round($sample['t'] - $startS, 2);

                if ($sample['d'] > $segment->start_m && $sample['d'] < $segment->end_m && $t > 0 && $t < $timeS) {
                    $points[] = [$t, (float) $sample['d']];
                }
            }
        }

        $points[] = [$timeS, (float) $segment->end_m];

        return $points;
    }

    /**
     * The ride clock when the rider crossed `$distance`, interpolated between the two samples around it.
     *
     * @param  list<array{t: int|float, d: int|float}>  $samples
     */
    private function timeAt(array $samples, int $distance): ?float
    {
        foreach ($samples as $i => $sample) {
            if ($sample['d'] < $distance) {
                continue;
            }

            $before = $samples[$i - 1] ?? null;

            if ($before === null || $sample['d'] <= $before['d']) {
                return (float) $sample['t'];
            }

            return $before['t'] + ($sample['t'] - $before['t']) * ($distance - $before['d']) / ($sample['d'] - $before['d']);
        }

        return null;
    }
}
