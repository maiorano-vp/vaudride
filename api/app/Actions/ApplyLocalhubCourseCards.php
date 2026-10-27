<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ImportSource;
use App\Models\Course;
use App\Models\CourseImport;
use App\Models\Poi;
use App\Services\LocalhubClient;
use App\Services\LocalhubPlaceMapper;

/**
 * Fills what a course card shows (photo, copyright, tourism destination, kind of ride) from the Local Hub tour each
 * course was imported from, found by the address of its GPX. What the admin typed is kept unless `$force`. A course the
 * tours do not give a photo falls back on the photo of one of its places.
 */
final readonly class ApplyLocalhubCourseCards
{
    /** Tour categories that are rides, in the order they win a course that is in both. */
    public const array KINDS = ['velo', 'mtb'];

    /** Wide enough for a card on a large screen. */
    private const int IMAGE_WIDTH = 800;

    public function __construct(private LocalhubClient $localhub, private LocalhubPlaceMapper $mapper) {}

    /**
     * @return array{tours: int, matched: int, updated: int, unmatched: int, fallback: int}
     */
    public function handle(bool $dryRun, bool $force): array
    {
        $result = ['tours' => 0, 'matched' => 0, 'updated' => 0, 'unmatched' => 0, 'fallback' => 0];
        $categories = collect($this->localhub->tourCategories())->keyBy('slug');
        $courseByGpx = CourseImport::query()
            ->where('source', ImportSource::Localhub)->whereNotNull('course_slug')->whereNotNull('source_url')
            ->pluck('course_slug', 'source_url');

        foreach (self::KINDS as $kind) {
            $categoryId = $categories[$kind]['id'] ?? null;

            if ($categoryId === null) {
                continue;
            }

            foreach ($this->localhub->tours($categoryId) as $tour) {
                $result['tours']++;
                $slug = $courseByGpx[(string) ($tour['route'] ?? '')] ?? null;
                $course = $slug === null ? null : Course::query()->where('slug', $slug)->first();

                if ($course === null) {
                    $result['unmatched']++;

                    continue;
                }

                $result['matched']++;
                $card = $this->mapper->image($tour, self::IMAGE_WIDTH) + ['destination' => $this->destination($tour), 'kind' => $this->guessKind($course) ?? $kind];
                $changes = $this->changes($course, $card, $force);

                if ($changes !== []) {
                    $result['updated']++;

                    if (! $dryRun) {
                        $course->update($changes);
                    }
                }
            }
        }

        $this->fillMissingKinds($dryRun);
        $result['fallback'] = $this->fallbackToPlaceImages($dryRun);

        return $result;
    }

    /**
     * What the name says about the ride, when Local Hub does not: all its tours are in the "velo" category, even the
     * mountain-bike loops and the e-scooter tours.
     */
    private function guessKind(Course $course): ?string
    {
        $text = mb_strtolower(($course->name['fr'] ?? '').' '.$course->slug);

        return match (true) {
            preg_match('/\b(vtt|mtb)\b/', $text) === 1 => 'mtb',
            str_contains($text, 'trottinette') => 'scooter',
            default => null,
        };
    }

    /** Courses with no tour behind them (hand-made, or from another source) are told apart by their name, else rides on a bike. */
    private function fillMissingKinds(bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        foreach (Course::query()->where('published', true)->whereNull('kind')->get() as $course) {
            $course->update(['kind' => $this->guessKind($course) ?? 'velo']);
        }
    }

    /**
     * @param  array<string, mixed>  $tour
     */
    private function destination(array $tour): ?string
    {
        $name = mb_trim((string) ($tour['tenant']['name'] ?? ''));

        return $name === '' ? null : $name;
    }

    /**
     * The fields to write: the ones that have a new value and are empty on the course (or all of them when forced).
     *
     * @param  array<string, string|null>  $card
     * @return array<string, string>
     */
    private function changes(Course $course, array $card, bool $force): array
    {
        $changes = [];

        foreach ($card as $field => $value) {
            if ($value !== null && ($force || blank($course->getAttribute($field))) && $course->getAttribute($field) !== $value) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }

    /** Courses still without a photo take the first photo of one of their published places. */
    private function fallbackToPlaceImages(bool $dryRun): int
    {
        $count = 0;

        foreach (Course::query()->where('published', true)->whereNull('image_url')->get() as $course) {
            $poi = Poi::query()->where('course_id', $course->id)->where('published', true)->whereNotNull('image_url')->orderBy('distance_m')->first();

            if ($poi === null) {
                continue;
            }

            $count++;

            if (! $dryRun) {
                $course->update(['image_url' => $poi->image_url, 'image_credit' => $poi->image_credit]);
            }
        }

        return $count;
    }
}
