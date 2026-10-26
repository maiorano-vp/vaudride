<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PoiCategory;
use Carbon\CarbonInterface;
use Database\Factories\PoiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $course_id
 * @property-read int $distance_m
 * @property-read array{fr: string, de?: string, en?: string} $title
 * @property-read array{fr?: string, de?: string, en?: string}|null $description
 * @property-read float $lat
 * @property-read float $lon
 * @property-read string|null $url
 * @property-read PoiCategory|null $category
 * @property-read string $source
 * @property-read string|null $osm_id
 * @property-read string|null $external_id
 * @property-read string|null $address
 * @property-read string|null $image_url
 * @property-read string|null $image_credit
 * @property-read bool $published
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Poi extends Model
{
    /** @use HasFactory<PoiFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'course_id' => 'string',
            'distance_m' => 'integer',
            'title' => 'array',
            'description' => 'array',
            'lat' => 'float',
            'lon' => 'float',
            'url' => 'string',
            'category' => PoiCategory::class,
            'osm_id' => 'string',
            'external_id' => 'string',
            'address' => 'string',
            'published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
