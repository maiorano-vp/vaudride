<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $course_id
 * @property-read CarbonInterface $started_at
 * @property-read int $duration_s
 * @property-read int $distance_m
 * @property-read int $elevation_gain_m
 * @property-read int|null $avg_power_w
 * @property-read int|null $max_power_w
 * @property-read int|null $avg_heart_rate
 * @property-read int|null $strava_upload_id
 * @property-read int|null $strava_activity_id
 * @property-read string|null $strava_error
 * @property-read list<array{t: int, d: float, p: int, c?: int|null, h?: int|null, s: float}> $samples
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'course_id' => 'string',
            'started_at' => 'datetime',
            'duration_s' => 'integer',
            'distance_m' => 'integer',
            'elevation_gain_m' => 'integer',
            'avg_power_w' => 'integer',
            'max_power_w' => 'integer',
            'avg_heart_rate' => 'integer',
            'strava_upload_id' => 'integer',
            'strava_activity_id' => 'integer',
            'strava_error' => 'string',
            'samples' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Everything except the (large) samples column, for list views.
     *
     * @param  Builder<Ride>  $query
     */
    public function scopeSummary(Builder $query): void
    {
        $query->select([
            'id', 'user_id', 'course_id', 'started_at', 'duration_s', 'distance_m',
            'elevation_gain_m', 'avg_power_w', 'max_power_w', 'avg_heart_rate', 'strava_upload_id', 'strava_activity_id',
            'strava_error', 'created_at', 'updated_at',
        ]);
    }
}
