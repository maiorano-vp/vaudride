<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidGpxException;
use SimpleXMLElement;
use XMLWriter;

/**
 * A parsed GPX track: geometry for previews and trimming. Distances use haversine (about 0.1 % off the pipeline's
 * LV95 figures); the pipeline stays the source of truth for the final profile.
 */
final readonly class GpxRoute
{
    private const int MAX_BYTES = 10_000_000;

    private const int PREVIEW_POINTS = 400;

    private const int CLIMB_THRESHOLD_M = 3;

    /** @var list<float> cumulative distance in metres, one per point */
    public array $distances;

    /**
     * @param  list<array{lat: float, lon: float, ele: float|null}>  $points
     * @param  list<array{name: string, lat: float, lon: float}>  $waypoints
     */
    public function __construct(public array $points, public array $waypoints = [], public ?string $name = null)
    {
        $distances = [];
        $total = 0.0;
        foreach ($points as $i => $point) {
            if ($i > 0) {
                $previous = $points[$i - 1];
                $total += self::haversineM($previous['lat'], $previous['lon'], $point['lat'], $point['lon']);
            }
            $distances[] = $total;
        }
        $this->distances = $distances;
    }

    /**
     * @throws InvalidGpxException
     */
    public static function parse(string $gpx): self
    {
        if ($gpx === '' || mb_strlen($gpx, '8bit') > self::MAX_BYTES) {
            throw new InvalidGpxException('The GPX file is empty or larger than 10 MB.');
        }
        if (str_contains($gpx, '<!DOCTYPE') || str_contains($gpx, '<!ENTITY')) {
            throw new InvalidGpxException('GPX files with a DOCTYPE or entities are not accepted.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($gpx, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement || $xml->getName() !== 'gpx') {
            throw new InvalidGpxException('Not a valid GPX file.');
        }

        $nodes = $xml->xpath('//*[local-name()="trkpt"]') ?: $xml->xpath('//*[local-name()="rtept"]') ?: [];
        $points = [];
        foreach ($nodes as $node) {
            $coordinates = self::coordinates($node);
            if ($coordinates === null) {
                throw new InvalidGpxException('A track point has invalid coordinates.');
            }
            $elevation = self::childText($node, 'ele');
            $points[] = [...$coordinates, 'ele' => is_numeric($elevation) ? (float) $elevation : null];
        }
        if (count($points) < 2) {
            throw new InvalidGpxException('The GPX file needs a track with at least 2 points.');
        }

        $waypoints = [];
        foreach ($xml->xpath('//*[local-name()="wpt"]') ?: [] as $node) {
            $coordinates = self::coordinates($node);
            if ($coordinates !== null) {
                $waypoints[] = ['name' => self::childText($node, 'name') ?? '', ...$coordinates];
            }
        }

        $title = ($xml->xpath('//*[local-name()="metadata"]/*[local-name()="name"]') ?: $xml->xpath('//*[local-name()="trk"]/*[local-name()="name"]') ?: [])[0] ?? null;
        $name = $title instanceof SimpleXMLElement ? mb_trim((string) $title) : '';

        return new self($points, $waypoints, $name === '' ? null : $name);
    }

    public function distanceM(): float
    {
        return array_last($this->distances) ?? 0.0;
    }

    /**
     * Climb in metres. Each rise only counts once it exceeds 3 m above the last low point, so GPS altitude noise
     * is ignored while steady climbs are summed in full.
     */
    public function elevationGainM(): float
    {
        $elevations = array_column($this->points, 'ele');
        if (in_array(null, $elevations, true) || $elevations === []) {
            return 0.0;
        }

        $reference = $elevations[0];
        $gain = 0.0;
        foreach ($elevations as $elevation) {
            if ($elevation - $reference >= self::CLIMB_THRESHOLD_M) {
                $gain += $elevation - $reference;
                $reference = $elevation;
            } elseif ($elevation < $reference) {
                $reference = $elevation;
            }
        }

        return $gain;
    }

    /**
     * Downsampled altitude profile for charts.
     *
     * @return list<array{d: float, ele: float|null}>
     */
    public function preview(): array
    {
        $step = max(1, intdiv(count($this->points), self::PREVIEW_POINTS));
        $last = array_key_last($this->points);
        $preview = [];
        foreach ($this->points as $i => $point) {
            if ($i % $step === 0 || $i === $last) {
                $preview[] = ['d' => round($this->distances[$i], 1), 'ele' => $point['ele'] === null ? null : round($point['ele'], 1)];
            }
        }

        return $preview;
    }

    /**
     * @return array{distance_m: float, elevation_gain_m: float, point_count: int, bbox: array{0: float, 1: float, 2: float, 3: float}, waypoints: list<array{name: string, lat: float, lon: float, d: float}>}
     */
    public function summary(): array
    {
        $lats = array_column($this->points, 'lat');
        $lons = array_column($this->points, 'lon');

        return [
            'distance_m' => round($this->distanceM(), 1),
            'elevation_gain_m' => round($this->elevationGainM(), 1),
            'point_count' => count($this->points),
            'bbox' => $lats === [] || $lons === [] ? [0.0, 0.0, 0.0, 0.0] : [min($lons), min($lats), max($lons), max($lats)],
            'waypoints' => array_map(
                fn (array $waypoint): array => [...$waypoint, 'd' => round($this->nearestDistanceM($waypoint['lat'], $waypoint['lon']), 1)],
                $this->waypoints,
            ),
        ];
    }

    /**
     * Keeps the points between two distances (metres along the original track), optionally reversed.
     *
     * @throws InvalidGpxException
     */
    public function trim(?float $startM, ?float $endM, bool $reversed): self
    {
        $start = max(0.0, $startM ?? 0.0);
        $end = $endM ?? $this->distanceM();

        $kept = [];
        foreach ($this->points as $i => $point) {
            if ($this->distances[$i] >= $start && $this->distances[$i] <= $end) {
                $kept[] = $point;
            }
        }
        if (count($kept) < 2) {
            throw new InvalidGpxException('The trim range keeps fewer than 2 points.');
        }

        return new self($reversed ? array_reverse($kept) : $kept, [], $this->name);
    }

    /**
     * Distance along the track of the point closest to a position (used to place waypoints).
     */
    public function nearestDistanceM(float $lat, float $lon): float
    {
        $best = 0;
        $bestDistance = INF;
        foreach ($this->points as $i => $point) {
            $distance = self::haversineM($lat, $lon, $point['lat'], $point['lon']);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $i;
            }
        }

        return $this->distances[$best];
    }

    /**
     * Coordinates only: altitudes are re-sampled from swissALTI3D by the pipeline, so none are carried over.
     */
    public function toGpx(string $name, string $description): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('gpx');
        $xml->writeAttribute('version', '1.1');
        $xml->writeAttribute('creator', 'vaudride');
        $xml->writeAttribute('xmlns', 'http://www.topografix.com/GPX/1/1');
        $xml->startElement('metadata');
        $xml->writeElement('name', $name);
        $xml->writeElement('desc', $description);
        $xml->endElement();
        $xml->startElement('trk');
        $xml->writeElement('name', $name);
        $xml->startElement('trkseg');
        foreach ($this->points as $point) {
            $xml->startElement('trkpt');
            $xml->writeAttribute('lat', number_format($point['lat'], 6, '.', ''));
            $xml->writeAttribute('lon', number_format($point['lon'], 6, '.', ''));
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        return $xml->outputMemory();
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private static function coordinates(SimpleXMLElement $node): ?array
    {
        $lat = (string) $node['lat'];
        $lon = (string) $node['lon'];
        if (! is_numeric($lat) || ! is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
            return null;
        }

        return ['lat' => (float) $lat, 'lon' => (float) $lon];
    }

    private static function childText(SimpleXMLElement $node, string $name): ?string
    {
        $child = ($node->xpath('*[local-name()="'.$name.'"]') ?: [])[0] ?? null;

        return $child instanceof SimpleXMLElement ? mb_trim((string) $child) : null;
    }

    private static function haversineM(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6_371_008.8 * 2 * asin(min(1.0, sqrt($a)));
    }
}
