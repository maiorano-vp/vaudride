<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
final class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'distance_m' => $this->distance_m,
            'elevation_gain_m' => $this->elevation_gain_m,
            'attribution' => $this->attribution,
            'profile_url' => sprintf('%s/%s/profile.json?v=%d', mb_rtrim(config()->string('services.courses.cdn_url'), '/'), $this->slug, $this->version),
            'pois' => PoiResource::collection($this->whenLoaded('pois')),
            'segments' => SegmentResource::collection($this->whenLoaded('segments')),
        ];
    }
}
