<?php

declare(strict_types=1);

use App\Exceptions\InvalidGpxException;
use App\Services\GpxRoute;
use Database\Factories\CourseImportFactory;

function sampleRoute(): GpxRoute
{
    return GpxRoute::parse(CourseImportFactory::sampleGpx());
}

it('parses points, name, waypoints and distances', function (): void {
    $route = sampleRoute();

    expect($route->points)->toHaveCount(12)
        ->and($route->name)->toBe('Sample climb')
        ->and($route->waypoints)->toHaveCount(1)
        ->and($route->distanceM())->toEqualWithDelta(1101, 5)
        ->and($route->elevationGainM())->toEqualWithDelta(99, 5)
        ->and($route->nearestDistanceM(46.5045, 6.3))->toEqualWithDelta(500, 5);
});

it('summarises with a bounding box, waypoint positions and a preview', function (): void {
    $summary = sampleRoute()->summary();

    expect($summary['point_count'])->toBe(12)
        ->and($summary['bbox'][1])->toEqualWithDelta(46.5, 0.0001)
        ->and($summary['waypoints'][0]['name'])->toBe('Halfway')
        ->and($summary['waypoints'][0]['d'])->toEqualWithDelta(500, 5)
        ->and(sampleRoute()->preview())->toHaveCount(12);
});

it('trims by distance and can reverse', function (): void {
    $trimmed = sampleRoute()->trim(200, 600, false);

    expect($trimmed->distanceM())->toEqualWithDelta(400, 110)
        ->and($trimmed->points[0]['lat'])->toBeLessThan($trimmed->points[array_key_last($trimmed->points)]['lat']);

    $reversed = sampleRoute()->trim(200, 600, true);
    expect($reversed->points[0]['lat'])->toBeGreaterThan($reversed->points[array_key_last($reversed->points)]['lat']);
});

it('refuses a trim that keeps fewer than two points', function (): void {
    sampleRoute()->trim(300, 310, false);
})->throws(InvalidGpxException::class);

it('writes coordinates only, without altitudes', function (): void {
    $gpx = sampleRoute()->toGpx('Test', 'desc');

    expect($gpx)->toContain('<trkpt')->not->toContain('<ele>');
    expect(GpxRoute::parse($gpx)->points)->toHaveCount(12);
});

it('rejects invalid GPX', function (string $gpx): void {
    GpxRoute::parse($gpx);
})->throws(InvalidGpxException::class)->with([
    'empty' => '',
    'not xml' => 'hello',
    'not gpx' => '<kml></kml>',
    'one point' => '<gpx><trk><trkseg><trkpt lat="46" lon="6"/></trkseg></trk></gpx>',
    'bad latitude' => '<gpx><trk><trkseg><trkpt lat="146" lon="6"/><trkpt lat="46" lon="6"/></trkseg></trk></gpx>',
    'doctype' => '<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY x "y">]><gpx><trk><trkseg><trkpt lat="46" lon="6"/><trkpt lat="46.1" lon="6"/></trkseg></trk></gpx>',
]);
