<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SyncLocalhubPlaces;
use App\Services\LocalhubClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:sync')]
#[Description('Refreshes the local copy of the Local Hub catalogue (takes several minutes)')]
final class LocalhubSyncCommand extends Command
{
    public function handle(LocalhubClient $localhub, SyncLocalhubPlaces $sync): int
    {
        if (! $localhub->configured()) {
            $this->error('LOCALHUB_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        // the catalogue is read page after page: about ten seconds each
        set_time_limit(0);
        $this->info('Reading the Local Hub catalogue…');

        $result = $sync->handle();

        $this->info("{$result['synced']} places synced, {$result['without_position']} without a position left out, {$result['removed']} removed.");

        return self::SUCCESS;
    }
}
