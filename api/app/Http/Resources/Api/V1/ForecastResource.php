<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CourseForecast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseForecast
 */
final class ForecastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'day' => $this->day->toDateString(),
            'temp_min' => $this->temp_min,
            'temp_max' => $this->temp_max,
            'precipitation_mm' => $this->precipitation_mm,
            'pictogram' => $this->pictogram,
            'place' => $this->place,
        ];
    }
}
