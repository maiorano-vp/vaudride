<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $slug
 * @property-read array{fr: string, de?: string, en?: string} $name
 * @property-read array{fr?: string, de?: string, en?: string}|null $description
 * @property-read list<int>|null $best_months
 * @property-read int $version
 * @property-read int $distance_m
 * @property-read int $elevation_gain_m
 * @property-read string $attribution
 * @property-read bool $published
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'slug' => 'string',
            'name' => 'array',
            'description' => 'array',
            'best_months' => 'array',
            'version' => 'integer',
            'distance_m' => 'integer',
            'elevation_gain_m' => 'integer',
            'attribution' => 'string',
            'published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Poi, $this>
     */
    public function pois(): HasMany
    {
        return $this->hasMany(Poi::class)->orderBy('distance_m');
    }

    /**
     * @return HasMany<CourseForecast, $this>
     */
    public function forecasts(): HasMany
    {
        return $this->hasMany(CourseForecast::class)->orderBy('day');
    }

    /**
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * @return HasMany<Segment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class)->orderBy('start_m');
    }
}
