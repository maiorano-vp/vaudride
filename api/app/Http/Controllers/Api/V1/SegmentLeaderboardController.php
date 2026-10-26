<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\SegmentLeaderboard;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SegmentLeaderboardController
{
    public function __invoke(Request $request, SegmentLeaderboard $leaderboard, string $segment): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $segment = Segment::query()->where('published', true)->findOrFail($segment);
        $limit = min(max($request->integer('limit', 20), 1), 50);

        return response()->json([
            'data' => array_map(fn (array $row): array => [
                'rank' => $row['rank'],
                'nickname' => $row['nickname'],
                'time_s' => $row['time_s'],
                'avg_power_w' => $row['avg_power_w'],
                'achieved_at' => $row['achieved_at']->toIso8601String(),
                'is_me' => $row['user_id'] === $user->id,
            ], $leaderboard->top($segment, $limit)),
            'me' => $leaderboard->of($segment, $user),
        ]);
    }
}
