<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
final class RideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course' => new CourseResource($this->whenLoaded('course')),
            'started_at' => $this->started_at->toIso8601String(),
            'duration_s' => $this->duration_s,
            'distance_m' => $this->distance_m,
            'elevation_gain_m' => $this->elevation_gain_m,
            'avg_power_w' => $this->avg_power_w,
            'max_power_w' => $this->max_power_w,
            'avg_heart_rate' => $this->avg_heart_rate,
            'strava_activity_id' => $this->strava_activity_id,
            'strava_error' => $this->strava_error,
            'efforts' => SegmentEffortResource::collection($this->whenLoaded('efforts')),
            'samples' => $this->whenHas('samples'),
        ];
    }
}
