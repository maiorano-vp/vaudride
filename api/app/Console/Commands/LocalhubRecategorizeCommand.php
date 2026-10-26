<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LocalhubPlace;
use App\Services\LocalhubPlaceMapper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:recategorize')]
#[Description('Applies the current category rules to the local copy of the Local Hub catalogue, without reading the API')]
final class LocalhubRecategorizeCommand extends Command
{
    public function handle(LocalhubPlaceMapper $mapper): int
    {
        $changed = 0;

        LocalhubPlace::query()->each(function (LocalhubPlace $place) use ($mapper, &$changed): void {
            $category = $mapper->categorize($place->title['fr'], $place->facts ?? []);

            if ($category !== $place->category) {
                $place->forceFill(['category' => $category])->save();
                $changed++;
            }
        });

        $this->info("{$changed} places changed category.");

        return self::SUCCESS;
    }
}
