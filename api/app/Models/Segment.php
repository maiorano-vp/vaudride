<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClimbCategory;
use Carbon\CarbonInterface;
use Database\Factories\SegmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stretch of a course that riders are timed on (a climb, mostly).
 *
 * @property-read string $id
 * @property-read string $course_id
 * @property-read array{fr: string, de?: string, en?: string} $name
 * @property-read int $start_m
 * @property-read int $end_m
 * @property-read ClimbCategory|null $category
 * @property-read bool $published
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Segment extends Model
{
    /** @use HasFactory<SegmentFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, mixed>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'course_id' => 'string',
            'name' => 'array',
            'start_m' => 'integer',
            'end_m' => 'integer',
            'category' => ClimbCategory::class,
            'published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SegmentEffort, $this>
     */
    public function efforts(): HasMany
    {
        return $this->hasMany(SegmentEffort::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
