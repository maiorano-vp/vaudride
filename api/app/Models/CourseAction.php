<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourseActionType;
use Carbon\CarbonInterface;
use Database\Factories\CourseActionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many times, on one day, visitors took one step towards riding a course for real. A total: nobody is identified.
 *
 * @property-read string $id
 * @property-read string $course_id
 * @property-read CourseActionType $action
 * @property-read CarbonInterface $day
 * @property-read int $count
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class CourseAction extends Model
{
    /** @use HasFactory<CourseActionFactory> */
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
            'action' => CourseActionType::class,
            'day' => 'date:Y-m-d',
            'count' => 'integer',
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
