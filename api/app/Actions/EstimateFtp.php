<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ride;
use App\Models\User;

/**
 * Estimates a player's FTP from their rides: 95 % of their best average power over 20 uninterrupted minutes.
 * An estimate to suggest, never to apply by itself: a 20-minute effort the player did not give all they had gives a low one.
 */
final readonly class EstimateFtp
{
    private const int WINDOW_S = 1200;

    private const float SHARE_OF_BEST = 0.95;

    /** Only recent form says what the FTP is now. */
    private const int LOOKBACK_DAYS = 365;

    /**
     * @return array{ftp: int, avg_power_w: int, ride_id: string, achieved_at: string}|null null when no ride has 20 minutes of data
     */
    public function handle(User $user): ?array
    {
        $best = null;

        $rides = $user->rides()
            ->where('duration_s', '>=', self::WINDOW_S)
            ->where('started_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->select(['id', 'user_id', 'started_at', 'samples'])
            ->lazyById(20);

        /** @var Ride $ride */
        foreach ($rides as $ride) {
            $power = $this->bestWindowPower($ride->samples);

            if ($power !== null && ($best === null || $power > $best['power'])) {
                $best = ['power' => $power, 'ride' => $ride];
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'ftp' => max(50, min(600, (int) round($best['power'] * self::SHARE_OF_BEST))),
            'avg_power_w' => (int) round($best['power']),
            'ride_id' => $best['ride']->id,
            'achieved_at' => $best['ride']->started_at->toIso8601String(),
        ];
    }

    /**
     * The best average power over any stretch of 20 minutes of the ride clock (which stops when the ride is paused).
     *
     * @param  list<array{t: int|float, p: int|float}>  $samples
     */
    private function bestWindowPower(array $samples): ?float
    {
        $best = null;
        $sum = 0;
        $start = 0;

        foreach ($samples as $end => $sample) {
            $sum += $sample['p'];

            while ($sample['t'] - $samples[$start]['t'] >= self::WINDOW_S) {
                $sum -= $samples[$start]['p'];
                $start++;
            }

            // a full window: it spans (almost) the 20 minutes, with a sample for most of its seconds
            $span = $sample['t'] - $samples[$start]['t'];
            $count = $end - $start + 1;

            if ($span >= self::WINDOW_S - 1 && $count >= self::WINDOW_S * 0.9) {
                $average = $sum / $count;
                $best = $best === null ? $average : max($best, $average);
            }
        }

        return $best;
    }
}
