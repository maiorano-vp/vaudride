<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ApplyLocalhubCourseCards;
use App\Services\LocalhubClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:course-cards {--dry-run : Only count what would change} {--force : Replace what is already there, the admin\'s edits included}')]
#[Description('Fills the photo, destination and kind of each course card from the Local Hub tour it was imported from')]
final class LocalhubCourseCardsCommand extends Command
{
    public function handle(LocalhubClient $localhub, ApplyLocalhubCourseCards $cards): int
    {
        if (! $localhub->configured()) {
            $this->error('LOCALHUB_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $cards->handle($dryRun, (bool) $this->option('force'));

        $this->info(sprintf(
            '%d tours read, %d matched to a course, %s updated, %d without a course here; %d courses %s a place photo.',
            $result['tours'],
            $result['matched'],
            $dryRun ? $result['updated'].' would be' : (string) $result['updated'],
            $result['unmatched'],
            $result['fallback'],
            $dryRun ? 'would take' : 'took',
        ));

        return self::SUCCESS;
    }
}
