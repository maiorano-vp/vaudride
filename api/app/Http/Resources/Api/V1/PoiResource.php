<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Poi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poi
 */
final class PoiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distance_m' => $this->distance_m,
            'title' => $this->title,
            'description' => $this->description,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'url' => $this->url,
            'category' => $this->category?->value,
            'address' => $this->address,
            'source' => $this->source,
        ];
    }
}
