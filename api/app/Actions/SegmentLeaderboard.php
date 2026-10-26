<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Segment;
use App\Models\SegmentEffort;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Ranks the riders of a segment by their best time. Ties share a rank. */
final readonly class SegmentLeaderboard
{
    /**
     * @return list<array{rank: int, user_id: string, nickname: string, time_s: float, avg_power_w: int|null, achieved_at: CarbonInterface}>
     */
    public function top(Segment $segment, int $limit): array
    {
        $best = SegmentEffort::query()
            ->where('segment_id', $segment->id)
            ->select('user_id', DB::raw('MIN(time_s) as best_s'))
            ->groupBy('user_id')
            ->orderBy('best_s')
            ->limit($limit)
            ->get();

        $efforts = SegmentEffort::query()->where('segment_id', $segment->id)->whereIn('user_id', $best->pluck('user_id'))->get();
        $names = User::query()->whereIn('id', $best->pluck('user_id'))->pluck('nickname', 'id');
        $rows = [];
        $previous = null;
        $rank = 0;

        foreach ($best as $i => $row) {
            $time = round((float) $row->getAttribute('best_s'), 2);
            $effort = $efforts->first(fn (SegmentEffort $e): bool => $e->user_id === $row->user_id && abs($e->time_s - $time) < 0.005);
            $rank = $previous !== null && $time === $previous ? $rank : $i + 1;
            $previous = $time;

            if ($effort === null) {
                continue;
            }

            $rows[] = [
                'rank' => $rank,
                'user_id' => $row->user_id,
                'nickname' => (string) ($names[$row->user_id] ?? '?'),
                'time_s' => $time,
                'avg_power_w' => $effort->avg_power_w,
                'achieved_at' => $effort->started_at,
            ];
        }

        return $rows;
    }

    /** Rank a time would have among the riders' best times (1 = KOM). */
    public function rankOf(Segment $segment, float $timeS): int
    {
        // compared in PHP: a float bound into SQL travels as text, and SQLite then calls every number smaller than it
        $faster = SegmentEffort::query()
            ->where('segment_id', $segment->id)
            ->groupBy('user_id')
            ->selectRaw('MIN(time_s) as best_s')
            ->pluck('best_s')
            ->filter(fn (mixed $best): bool => round((float) $best, 2) < round($timeS, 2))
            ->count();

        return $faster + 1;
    }

    /**
     * @return array{rank: int, time_s: float}|null
     */
    public function of(Segment $segment, User $user): ?array
    {
        $best = SegmentEffort::query()->where('segment_id', $segment->id)->where('user_id', $user->id)->min('time_s');

        return $best === null ? null : ['rank' => $this->rankOf($segment, (float) $best), 'time_s' => round((float) $best, 2)];
    }
}
