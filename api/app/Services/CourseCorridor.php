<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\StoreCourseProfile;
use App\Models\Course;
use Illuminate\Support\Facades\Storage;

/** The road of a course, from its published profile: where a place lies along it, and how far off the road. */
final readonly class CourseCorridor
{
    /**
     * @param  list<array{d: float|int, lat: float|int, lon: float|int}>  $points
     */
    private function __construct(private array $points) {}

    /** The corridor of a course; null when its profile was not built yet. */
    public static function forCourse(Course $course): ?self
    {
        $disk = Storage::disk(StoreCourseProfile::DISK);
        $path = StoreCourseProfile::path($course->slug);

        if (! $disk->exists($path)) {
            return null;
        }

        /** @var array{points: list<array{d: float|int, lat: float|int, lon: float|int}>} $profile */
        $profile = json_decode((string) $disk->get($path), true, flags: JSON_THROW_ON_ERROR);

        return new self($profile['points']);
    }

    /**
     * A box around the route, grown by `$margin` degrees (0.04 is about 4.4 km): keeps a query from handing over a whole catalogue.
     *
     * @return array{lat: array{float, float}, lon: array{float, float}}
     */
    public function box(float $margin): array
    {
        $lats = array_column($this->points, 'lat');
        $lons = array_column($this->points, 'lon');

        return [
            'lat' => [min($lats) - $margin, max($lats) + $margin],
            'lon' => [min($lons) - $margin, max($lons) + $margin],
        ];
    }

    /**
     * Distance along the course of the route point nearest to the place, and how far off the road the place is (m).
     *
     * @return array{float, float}
     */
    public function along(float $lat, float $lon): array
    {
        $best = [0.0, INF];

        foreach ($this->points as $p) {
            $meters = $this->metres($p['lat'], $p['lon'], $lat, $lon);

            if ($meters < $best[1]) {
                $best = [(float) $p['d'], $meters];
            }
        }

        return $best;
    }

    /** How far the place is from the start or from the finish, whichever is nearer (m). */
    public function nearEnds(float $lat, float $lon): float
    {
        $first = $this->points[0];
        $last = $this->points[array_key_last($this->points)];

        return min($this->metres($first['lat'], $first['lon'], $lat, $lon), $this->metres($last['lat'], $last['lon'], $lat, $lon));
    }

    private function metres(float|int $lat1, float|int $lon1, float $lat2, float $lon2): float
    {
        $dy = ($lat2 - $lat1) * 111_320;
        $dx = ($lon2 - $lon1) * 111_320 * cos(deg2rad($lat2));

        return sqrt($dx * $dx + $dy * $dy);
    }
}
