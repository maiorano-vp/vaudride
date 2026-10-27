<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\SegmentGhost;
use App\Models\Segment;
use App\Models\SegmentEffort;
use Illuminate\Http\JsonResponse;

/**
 * The segment record (KOM) as a ghost: where its holder was, second by second, from the start line.
 * The nickname is the one the leaderboard already shows; nothing else about the rider is sent.
 */
final readonly class SegmentGhostController
{
    public function __invoke(SegmentGhost $ghost, string $segment): JsonResponse
    {
        $segment = Segment::query()->where('published', true)->findOrFail($segment);
        $record = SegmentEffort::query()->where('segment_id', $segment->id)->with(['ride', 'user'])->orderBy('time_s')->firstOrFail();

        return response()->json([
            'data' => [
                'nickname' => $record->user->nickname,
                'time_s' => $record->time_s,
                'samples' => $ghost->handle($segment, $record),
            ],
        ]);
    }
}
