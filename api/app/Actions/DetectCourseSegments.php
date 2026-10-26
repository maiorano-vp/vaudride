<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Course;
use Illuminate\Support\Facades\Storage;

/** Adds the climbs of a course as segments: unpublished by default, for an admin to review. */
final readonly class DetectCourseSegments
{
    public function __construct(private DetectClimbs $detectClimbs) {}

    /**
     * @return int|null how many segments were created, null when the course has no profile yet
     */
    public function handle(Course $course, bool $publish = false): ?int
    {
        $disk = Storage::disk(StoreCourseProfile::DISK);
        $path = StoreCourseProfile::path($course->slug);

        if (! $disk->exists($path)) {
            return null;
        }

        /** @var array{points: list<array{d: float|int, ele: float|int}>} $profile */
        $profile = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $existing = $course->segments()->get(['start_m', 'end_m']);
        $created = 0;

        foreach ($this->detectClimbs->handle($profile['points']) as $climb) {
            $overlaps = $existing->contains(fn ($s): bool => $s->start_m < $climb['end_m'] && $s->end_m > $climb['start_m']);

            if ($overlaps) {
                continue;
            }

            $number = $existing->count() + $created + 1;
            $course->segments()->create([
                'name' => ['fr' => "Montée {$number}", 'de' => "Anstieg {$number}", 'en' => "Climb {$number}"],
                'start_m' => $climb['start_m'],
                'end_m' => $climb['end_m'],
                'category' => $climb['category'],
                'published' => $publish,
            ]);
            $created++;
        }

        return $created;
    }
}
