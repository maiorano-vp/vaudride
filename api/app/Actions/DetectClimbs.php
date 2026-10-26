<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ClimbCategory;

/** Finds the climbs of a course profile: sustained ascents that tolerate small dips. */
final readonly class DetectClimbs
{
    private const float DIP_M = 12; // a drop this deep ends the climb

    private const float MIN_LENGTH_M = 500;

    private const float MIN_GAIN_M = 30;

    private const float MIN_GRADE_PCT = 3;

    /**
     * @param  list<array{d: float|int, ele: float|int}>  $points  sorted by distance
     * @return list<array{start_m: int, end_m: int, gain_m: int, avg_grade: float, category: ClimbCategory|null}>
     */
    public function handle(array $points): array
    {
        $climbs = [];
        $valley = $peak = 0;

        foreach ($points as $i => $point) {
            if ($i === 0) {
                continue;
            }

            if ($point['ele'] <= $points[$valley]['ele'] && $peak === $valley) {
                // going down or flat before the climb: the valley keeps moving
                $valley = $peak = $i;
            } elseif ($point['ele'] > $points[$peak]['ele']) {
                $peak = $i;
            } elseif ($points[$peak]['ele'] - $point['ele'] >= self::DIP_M) {
                $climbs[] = $this->climb($points, $valley, $peak);
                $valley = $peak = $i;
            }
        }
        $climbs[] = $this->climb($points, $valley, $peak);

        return array_values(array_filter($climbs));
    }

    /**
     * @param  list<array{d: float|int, ele: float|int}>  $points
     * @return array{start_m: int, end_m: int, gain_m: int, avg_grade: float, category: ClimbCategory|null}|null
     */
    private function climb(array $points, int $valley, int $peak): ?array
    {
        $length = $points[$peak]['d'] - $points[$valley]['d'];
        $gain = $points[$peak]['ele'] - $points[$valley]['ele'];

        if ($length < self::MIN_LENGTH_M || $gain < self::MIN_GAIN_M) {
            return null;
        }

        $grade = $gain / $length * 100;

        if ($grade < self::MIN_GRADE_PCT) {
            return null;
        }

        return [
            'start_m' => (int) round($points[$valley]['d']),
            'end_m' => (int) round($points[$peak]['d']),
            'gain_m' => (int) round($gain),
            'avg_grade' => round($grade, 1),
            'category' => ClimbCategory::fromScore($length * $grade),
        ];
    }
}
