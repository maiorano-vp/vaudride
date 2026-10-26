<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportSource;
use App\Enums\LicenceStatus;
use Carbon\CarbonInterface;
use Database\Factories\CourseImportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A route submitted for review before it becomes a course: raw GPX plus the editor's trim, rights and naming.
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read ImportSource $source
 * @property-read string|null $source_url
 * @property-read string $title
 * @property-read string|null $author
 * @property-read LicenceStatus $licence_status
 * @property-read string $gpx
 * @property-read array<string, mixed> $summary
 * @property-read list<array{d: float, ele: float|null}> $preview
 * @property-read string|null $course_slug
 * @property-read float|null $trim_start_m
 * @property-read float|null $trim_end_m
 * @property-read bool $reversed
 * @property-read CarbonInterface|null $exported_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class CourseImport extends Model
{
    /** @use HasFactory<CourseImportFactory> */
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
            'source' => ImportSource::class,
            'source_url' => 'string',
            'title' => 'string',
            'author' => 'string',
            'licence_status' => LicenceStatus::class,
            'gpx' => 'string',
            'summary' => 'array',
            'preview' => 'array',
            'course_slug' => 'string',
            'trim_start_m' => 'float',
            'trim_end_m' => 'float',
            'reversed' => 'boolean',
            'exported_at' => 'datetime',
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
     * Everything except the large gpx and preview columns, for list views.
     *
     * @param  Builder<CourseImport>  $query
     */
    public function scopeListing(Builder $query): void
    {
        $query->select([
            'id', 'user_id', 'source', 'source_url', 'title', 'author', 'licence_status', 'summary',
            'course_slug', 'trim_start_m', 'trim_end_m', 'reversed', 'exported_at', 'created_at', 'updated_at',
        ]);
    }
}
