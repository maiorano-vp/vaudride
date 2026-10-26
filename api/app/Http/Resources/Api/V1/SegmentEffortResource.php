<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Actions\SegmentLeaderboard;
use App\Models\SegmentEffort;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SegmentEffort
 */
final class SegmentEffortResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rank = resolve(SegmentLeaderboard::class)->rankOf($this->segment, $this->time_s);

        return [
            'segment_id' => $this->segment_id,
            'name' => $this->segment->name,
            'time_s' => $this->time_s,
            'avg_power_w' => $this->avg_power_w,
            // rank among the riders' best times: 1 means this effort holds (or ties) the KOM
            'rank' => $rank,
            'is_kom' => $rank === 1,
        ];
    }
}
