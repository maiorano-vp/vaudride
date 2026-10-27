<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Course;
use App\Models\Poi;
use App\Models\Segment;

/**
 * What the German and English visitors would still see in French: the published courses, segments and places whose texts
 * (name, title, and the description when there is a French one) have no translation yet. The app falls back to French.
 */
final readonly class FindMissingTranslations
{
    public const array LOCALES = ['de', 'en'];

    /**
     * @return array{
     *     locales: array<string, array{total: int, missing: int}>,
     *     courses: list<array{id: string, slug: string, name: string, total: int, missing: array<string, int>}>
     * }
     */
    public function handle(): array
    {
        $totals = array_fill_keys(self::LOCALES, ['total' => 0, 'missing' => 0]);
        $perCourse = [];

        $courses = Course::query()->where('published', true)->get(['id', 'slug', 'name', 'description']);

        foreach ($courses as $course) {
            $perCourse[$course->id] = $this->count([$course->name, $course->description]);
        }

        $segments = Segment::query()->where('published', true)->whereIn('course_id', $courses->modelKeys())->get(['course_id', 'name']);

        foreach ($segments as $segment) {
            $perCourse[$segment->course_id] = $this->merge($perCourse[$segment->course_id], $this->count([$segment->name]));
        }

        $pois = Poi::query()->where('published', true)->whereIn('course_id', $courses->modelKeys())->get(['course_id', 'title', 'description']);

        foreach ($pois as $poi) {
            $perCourse[$poi->course_id] = $this->merge($perCourse[$poi->course_id], $this->count([$poi->title, $poi->description]));
        }

        $rows = [];

        foreach ($courses as $course) {
            $count = $perCourse[$course->id];

            foreach (self::LOCALES as $locale) {
                $totals[$locale]['total'] += $count['total'];
                $totals[$locale]['missing'] += $count['missing'][$locale];
            }

            if (array_sum($count['missing']) > 0) {
                $rows[] = [
                    'id' => $course->id,
                    'slug' => $course->slug,
                    'name' => $course->name['fr'] ?? $course->slug,
                    'total' => $count['total'],
                    'missing' => $count['missing'],
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => array_sum($b['missing']) <=> array_sum($a['missing']));

        return ['locales' => $totals, 'courses' => $rows];
    }

    /**
     * The texts among these translatable fields: those with a French text, and how many of them lack each locale.
     *
     * @param  list<array<string, string>|null>  $fields
     * @return array{total: int, missing: array<string, int>}
     */
    private function count(array $fields): array
    {
        $count = ['total' => 0, 'missing' => array_fill_keys(self::LOCALES, 0)];

        foreach ($fields as $field) {
            if (! filled($field['fr'] ?? null)) {
                continue;
            }

            $count['total']++;

            foreach (self::LOCALES as $locale) {
                if (! filled($field[$locale] ?? null)) {
                    $count['missing'][$locale]++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  array{total: int, missing: array<string, int>}  $a
     * @param  array{total: int, missing: array<string, int>}  $b
     * @return array{total: int, missing: array<string, int>}
     */
    private function merge(array $a, array $b): array
    {
        return [
            'total' => $a['total'] + $b['total'],
            'missing' => array_map(fn (string $locale): int => $a['missing'][$locale] + $b['missing'][$locale], array_combine(self::LOCALES, self::LOCALES)),
        ];
    }
}
