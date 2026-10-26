<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\CourseImport;
use App\Services\GpxRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseImport
 */
final class CourseImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'source_url' => $this->source_url,
            'title' => $this->title,
            'author' => $this->author,
            'licence_status' => $this->licence_status->value,
            'course_slug' => $this->course_slug,
            'trim_start_m' => $this->trim_start_m,
            'trim_end_m' => $this->trim_end_m,
            'reversed' => $this->reversed,
            'exported_at' => $this->exported_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'summary' => $this->summary,
            'preview' => $this->whenHas('preview'),
            'trimmed' => $this->whenHas('gpx', fn (): array => $this->trimmed()),
        ];
    }

    /**
     * What the export would produce with the saved trim and direction, so an editor sees the effect before exporting.
     *
     * @return array{distance_m: float, elevation_gain_m: float, preview: list<array{d: float, ele: float|null}>}
     */
    private function trimmed(): array
    {
        $route = GpxRoute::parse($this->gpx)->trim($this->trim_start_m, $this->trim_end_m, $this->reversed);

        return [
            'distance_m' => round($route->distanceM(), 1),
            'elevation_gain_m' => round($route->elevationGainM(), 1),
            'preview' => $route->preview(),
        ];
    }
}
