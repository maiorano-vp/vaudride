<?php

declare(strict_types=1);

use App\Models\Ride;
use App\Models\User;

/** Running the FIT CRC over a file that ends with its own CRC gives 0. */
function fitCrc(string $bytes): int
{
    $table = [0x0000, 0xCC01, 0xD801, 0x1400, 0xF001, 0x3C00, 0x2800, 0xE401, 0xA001, 0x6C00, 0x7800, 0xB401, 0x5000, 0x9C01, 0x8801, 0x4400];
    $crc = 0;
    foreach (mb_str_split($bytes, 1, '8bit') as $char) {
        $byte = ord($char);
        $crc = (($crc >> 4) & 0x0FFF) ^ $table[$crc & 0xF] ^ $table[$byte & 0xF];
        $crc = (($crc >> 4) & 0x0FFF) ^ $table[$crc & 0xF] ^ $table[($byte >> 4) & 0xF];
    }

    return $crc;
}

it('exports a ride as TCX', function (): void {
    $ride = Ride::factory()->create();

    $response = $this->actingAs($ride->user, 'sanctum')->get('/api/v1/rides/'.$ride->id.'/export/tcx');

    $response->assertOk()->assertHeader('Content-Type', 'application/vnd.garmin.tcx+xml');
    $xml = simplexml_load_string((string) $response->getContent());
    expect($xml)->not->toBeFalse();
    $xml->registerXPathNamespace('t', 'http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2');
    $xml->registerXPathNamespace('x', 'http://www.garmin.com/xmlschemas/ActivityExtension/v2');
    expect((string) $xml->Activities->Activity['Sport'])->toBe('Biking')
        ->and($xml->xpath('//t:Trackpoint'))->toHaveCount(3)
        ->and(array_map('strval', $xml->xpath('//x:Watts') ?: []))->toBe(['180', '200', '220']);
});

it('exports a ride as a valid FIT file', function (): void {
    $ride = Ride::factory()->create();

    $response = $this->actingAs($ride->user, 'sanctum')->get('/api/v1/rides/'.$ride->id.'/export/fit');

    $response->assertOk()->assertHeader('Content-Type', 'application/vnd.ant.fit');
    $bytes = (string) $response->getContent();
    $header = unpack('Csize/Cprotocol/vprofile/Vdata', $bytes);
    expect($header['size'])->toBe(14)
        ->and(mb_substr($bytes, 8, 4, '8bit'))->toBe('.FIT')
        ->and($header['data'])->toBe(mb_strlen($bytes, '8bit') - 16)
        ->and(fitCrc(mb_substr($bytes, 0, 14, '8bit')))->toBe(0) // header CRC
        ->and(fitCrc($bytes))->toBe(0); // file CRC
});

it('only exports my own rides, in known formats', function (): void {
    $ride = Ride::factory()->create();
    $other = User::factory()->create();

    $this->get('/api/v1/rides/'.$ride->id.'/export/fit', ['Accept' => 'application/json'])->assertUnauthorized();
    $this->actingAs($other, 'sanctum')->get('/api/v1/rides/'.$ride->id.'/export/fit')->assertNotFound();
    $this->actingAs($ride->user, 'sanctum')->get('/api/v1/rides/'.$ride->id.'/export/gpx')->assertNotFound();
});
