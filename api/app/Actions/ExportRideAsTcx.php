<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ride;
use XMLWriter;

final readonly class ExportRideAsTcx
{
    private const string NS = 'http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2';

    private const string NS_ACTIVITY = 'http://www.garmin.com/xmlschemas/ActivityExtension/v2';

    /**
     * Virtual ride without GPS: time, distance, heart rate, cadence, speed and power per sample.
     */
    public function handle(Ride $ride): string
    {
        $start = $ride->started_at->toImmutable()->utc();
        $iso = $start->format('Y-m-d\TH:i:s\Z');

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('TrainingCenterDatabase');
        $xml->writeAttribute('xmlns', self::NS);
        $xml->writeAttribute('xmlns:ns3', self::NS_ACTIVITY);
        $xml->startElement('Activities');
        $xml->startElement('Activity');
        $xml->writeAttribute('Sport', 'Biking');
        $xml->writeElement('Id', $iso);
        $xml->startElement('Lap');
        $xml->writeAttribute('StartTime', $iso);
        $xml->writeElement('TotalTimeSeconds', (string) $ride->duration_s);
        $xml->writeElement('DistanceMeters', (string) $ride->distance_m);
        $xml->writeElement('Calories', '0');
        $xml->writeElement('Intensity', 'Active');
        $xml->writeElement('TriggerMethod', 'Manual');
        $xml->startElement('Track');

        foreach ($ride->samples as $sample) {
            $xml->startElement('Trackpoint');
            $xml->writeElement('Time', $start->addSeconds($sample['t'])->format('Y-m-d\TH:i:s\Z'));
            $xml->writeElement('DistanceMeters', (string) round((float) $sample['d'], 1));
            if (($sample['h'] ?? null) !== null) {
                $xml->startElement('HeartRateBpm');
                $xml->writeElement('Value', (string) $sample['h']);
                $xml->endElement();
            }
            if (($sample['c'] ?? null) !== null) {
                $xml->writeElement('Cadence', (string) $sample['c']);
            }
            $xml->startElement('Extensions');
            $xml->startElement('ns3:TPX');
            $xml->writeElement('ns3:Speed', (string) round((float) $sample['s'] / 3.6, 3));
            $xml->writeElement('ns3:Watts', (string) $sample['p']);
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement(); // Track
        $xml->endElement(); // Lap
        $xml->endElement(); // Activity
        $xml->endElement(); // Activities
        $xml->endElement(); // TrainingCenterDatabase

        return $xml->outputMemory();
    }
}
