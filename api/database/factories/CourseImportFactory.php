<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportSource;
use App\Enums\LicenceStatus;
use App\Models\CourseImport;
use App\Models\User;
use App\Services\GpxRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseImport>
 */
final class CourseImportFactory extends Factory
{
    /**
     * A 1.1 km track heading north, climbing about 100 m, with one waypoint.
     */
    public static function sampleGpx(): string
    {
        $points = '';
        for ($i = 0; $i <= 11; $i++) {
            $points .= sprintf('<trkpt lat="%.6f" lon="6.300000"><ele>%d</ele></trkpt>', 46.5 + $i * 0.0009, 700 + $i * 9);
        }

        return '<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">'
            .'<metadata><name>Sample climb</name></metadata><wpt lat="46.5045" lon="6.3"><name>Halfway</name></wpt>'
            .'<trk><name>Sample</name><trkseg>'.$points.'</trkseg></trk></gpx>';
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gpx = self::sampleGpx();
        $route = GpxRoute::parse($gpx);

        return [
            'user_id' => User::factory(),
            'source' => ImportSource::GpxUpload,
            'source_url' => null,
            'title' => 'Sample climb',
            'author' => null,
            'licence_status' => LicenceStatus::Unknown,
            'gpx' => $gpx,
            'summary' => $route->summary(),
            'preview' => $route->preview(),
            'reversed' => false,
        ];
    }

    public function cleared(): self
    {
        return $this->state(fn (array $attributes): array => ['licence_status' => LicenceStatus::OwnWork, 'course_slug' => 'sample-climb']);
    }
}
