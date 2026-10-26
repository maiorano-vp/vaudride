<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Segment
 */
final class SegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_m' => $this->start_m,
            'end_m' => $this->end_m,
            'category' => $this->category?->value,
        ];
    }
}
