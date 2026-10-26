<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ride;

/**
 * Minimal FIT activity encoder (little endian): file_id, records, lap, session, activity.
 */
final readonly class ExportRideAsFit
{
    /** Seconds between the Unix epoch and the FIT epoch (1989-12-31 00:00:00 UTC). */
    private const int FIT_EPOCH_OFFSET = 631_065_600;

    private const int ENUM = 0x00;

    private const int UINT8 = 0x02;

    private const int UINT16 = 0x84;

    private const int UINT32 = 0x86;

    private const int UINT32Z = 0x8C;

    private const int INVALID_UINT8 = 0xFF;

    private const int INVALID_UINT16 = 0xFFFF;

    private const int MSG_FILE_ID = 0;

    private const int MSG_SESSION = 18;

    private const int MSG_LAP = 19;

    private const int MSG_RECORD = 20;

    private const int MSG_ACTIVITY = 34;

    private const int SPORT_CYCLING = 2;

    private const int SUB_SPORT_VIRTUAL_ACTIVITY = 58;

    /** @var array<int, int> */
    private const array CRC_TABLE = [
        0x0000, 0xCC01, 0xD801, 0x1400, 0xF001, 0x3C00, 0x2800, 0xE401,
        0xA001, 0x6C00, 0x7800, 0xB401, 0x5000, 0x9C01, 0x8801, 0x4400,
    ];

    public function handle(Ride $ride): string
    {
        $start = $ride->started_at->getTimestamp() - self::FIT_EPOCH_OFFSET;
        $end = $start + $ride->duration_s;
        $elapsedMs = $ride->duration_s * 1000;
        $distanceCm = $ride->distance_m * 100;

        $data = $this->message(0, self::MSG_FILE_ID, [
            [0, self::ENUM, 4], // type: activity
            [1, self::UINT16, 255], // manufacturer: development
            [2, self::UINT16, 0],
            [3, self::UINT32Z, 1],
            [4, self::UINT32, $start],
        ]);

        $recordDefinition = true;
        foreach ($ride->samples as $sample) {
            $data .= $this->message(1, self::MSG_RECORD, [
                [253, self::UINT32, $start + $sample['t']],
                [5, self::UINT32, (int) round((float) $sample['d'] * 100)],
                [6, self::UINT16, (int) round((float) $sample['s'] / 3.6 * 1000)],
                [7, self::UINT16, $sample['p']],
                [3, self::UINT8, $sample['h'] ?? self::INVALID_UINT8],
                [4, self::UINT8, $sample['c'] ?? self::INVALID_UINT8],
            ], definition: $recordDefinition);
            $recordDefinition = false;
        }

        $data .= $this->message(2, self::MSG_LAP, [
            [253, self::UINT32, $end],
            [2, self::UINT32, $start],
            [7, self::UINT32, $elapsedMs],
            [8, self::UINT32, $elapsedMs],
            [9, self::UINT32, $distanceCm],
            [0, self::ENUM, 9], // event: lap
            [1, self::ENUM, 1], // event type: stop
        ]);

        $data .= $this->message(3, self::MSG_SESSION, [
            [253, self::UINT32, $end],
            [2, self::UINT32, $start],
            [7, self::UINT32, $elapsedMs],
            [8, self::UINT32, $elapsedMs],
            [9, self::UINT32, $distanceCm],
            [5, self::ENUM, self::SPORT_CYCLING],
            [6, self::ENUM, self::SUB_SPORT_VIRTUAL_ACTIVITY],
            [20, self::UINT16, $ride->avg_power_w ?? self::INVALID_UINT16],
            [21, self::UINT16, $ride->max_power_w ?? self::INVALID_UINT16],
            [16, self::UINT8, $ride->avg_heart_rate ?? self::INVALID_UINT8],
            [22, self::UINT16, $ride->elevation_gain_m],
            [25, self::UINT16, 0], // first lap index
            [26, self::UINT16, 1], // number of laps
            [254, self::UINT16, 0], // message index
            [0, self::ENUM, 8], // event: session
            [1, self::ENUM, 1], // event type: stop
        ]);

        $data .= $this->message(4, self::MSG_ACTIVITY, [
            [253, self::UINT32, $end],
            [0, self::UINT32, $elapsedMs],
            [1, self::UINT16, 1], // number of sessions
            [2, self::ENUM, 0], // type: manual
            [3, self::ENUM, 26], // event: activity
            [4, self::ENUM, 1], // event type: stop
        ]);

        $header = pack('CCvV', 14, 0x20, 2132, mb_strlen($data, '8bit')).'.FIT';
        $header .= pack('v', $this->crc($header));

        return $header.$data.pack('v', $this->crc($header.$data));
    }

    /**
     * Definition message (optional) followed by one data message.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $fields  field number, base type, value
     */
    private function message(int $localType, int $globalType, array $fields, bool $definition = true): string
    {
        $out = '';

        if ($definition) {
            $out .= pack('CCCvC', 0x40 | $localType, 0, 0, $globalType, count($fields));
            foreach ($fields as [$number, $baseType]) {
                $out .= pack('CCC', $number, $this->size($baseType), $baseType);
            }
        }

        $out .= pack('C', $localType);
        foreach ($fields as [, $baseType, $value]) {
            $out .= match ($this->size($baseType)) {
                1 => pack('C', $value),
                2 => pack('v', $value),
                default => pack('V', $value),
            };
        }

        return $out;
    }

    private function size(int $baseType): int
    {
        return match ($baseType) {
            self::ENUM, self::UINT8 => 1,
            self::UINT16 => 2,
            default => 4,
        };
    }

    /**
     * FIT CRC-16 (nibble table), computed over the header and all data records.
     */
    private function crc(string $bytes): int
    {
        $crc = 0;
        foreach (mb_str_split($bytes, 1, '8bit') as $char) {
            $byte = ord($char);
            $crc = (($crc >> 4) & 0x0FFF) ^ self::CRC_TABLE[$crc & 0xF] ^ self::CRC_TABLE[$byte & 0xF];
            $crc = (($crc >> 4) & 0x0FFF) ^ self::CRC_TABLE[$crc & 0xF] ^ self::CRC_TABLE[($byte >> 4) & 0xF];
        }

        return $crc;
    }
}
