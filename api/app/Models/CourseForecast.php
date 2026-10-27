<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CourseForecastFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of weather at the start of a course. Source: MeteoSwiss.
 *
 * @property-read string $id
 * @property-read string $course_id
 * @property-read CarbonImmutable $day
 * @property-read float|null $temp_min
 * @property-read float|null $temp_max
 * @property-read float|null $precipitation_mm
 * @property-read int|null $pictogram
 * @property-read string $place
 * @property-read CarbonInterface $fetched_at
 */
final class CourseForecast extends Model
{
    /** @use HasFactory<CourseForecastFactory> */
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
            'day' => 'immutable_date',
            'temp_min' => 'float',
            'temp_max' => 'float',
            'precipitation_mm' => 'float',
            'pictogram' => 'integer',
            'place' => 'string',
            'fetched_at' => 'datetime',
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
