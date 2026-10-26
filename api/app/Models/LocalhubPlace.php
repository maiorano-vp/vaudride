<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PoiCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One place of the Local Hub catalogue, kept locally with what a point of interest needs.
 *
 * @property-read string $id
 * @property-read array{fr: string, de?: string, en?: string} $title
 * @property-read array{fr?: string, de?: string, en?: string}|null $summary
 * @property-read float $lat
 * @property-read float $lon
 * @property-read string|null $address
 * @property-read string|null $url
 * @property-read string|null $image_url
 * @property-read string|null $image_credit
 * @property-read PoiCategory $category
 * @property-read list<array{label: string, values: list<string>}>|null $facts
 * @property-read CarbonInterface $synced_at
 */
final class LocalhubPlace extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'title' => 'array',
            'summary' => 'array',
            'lat' => 'float',
            'lon' => 'float',
            'address' => 'string',
            'url' => 'string',
            'category' => PoiCategory::class,
            'facts' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
