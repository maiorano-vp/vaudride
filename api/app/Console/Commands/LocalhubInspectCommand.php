<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LocalhubClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:inspect')]
#[Description('Shows what the Local Hub API returns for one point of interest (to check the import mapping)')]
final class LocalhubInspectCommand extends Command
{
    public function handle(LocalhubClient $localhub): int
    {
        if (! $localhub->configured()) {
            $this->error('LOCALHUB_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        $hit = $localhub->sample();

        if ($hit === null) {
            $this->warn('The API answered, but returned no point of interest.');

            return self::SUCCESS;
        }

        $this->info('Fields: '.implode(', ', array_keys($hit)));
        $this->line((string) json_encode($hit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
