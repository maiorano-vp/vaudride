<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SegmentEffortFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How long a rider took on a segment during one ride, measured from the ride's distance/time samples.
 *
 * @property-read string $id
 * @property-read string $segment_id
 * @property-read string $ride_id
 * @property-read string $user_id
 * @property-read float $time_s
 * @property-read CarbonInterface $started_at
 * @property-read int|null $avg_power_w
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class SegmentEffort extends Model
{
    /** @use HasFactory<SegmentEffortFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'segment_id' => 'string',
            'ride_id' => 'string',
            'user_id' => 'string',
            'time_s' => 'float',
            'started_at' => 'datetime',
            'avg_power_w' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
