<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportLocalhubTours;
use App\Models\User;
use App\Services\LocalhubClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('localhub:tours {category? : Slug of a tour category (velo, mtb…); omit to list them} {--dry-run : Only list what would be imported} {--user= : Email of the admin who owns the imports (default: the first admin)}')]
#[Description('Lists the Local Hub tour categories, or imports the tours of one as GPX imports to review')]
final class LocalhubToursCommand extends Command
{
    public function handle(LocalhubClient $localhub, ImportLocalhubTours $import): int
    {
        if (! $localhub->configured()) {
            $this->error('LOCALHUB_TOKEN is not set in .env.');

            return self::FAILURE;
        }

        $categories = $localhub->tourCategories();

        if ($this->argument('category') === null) {
            $this->table(['Slug', 'Nom', 'Parcours'], array_map(fn (array $c): array => [$c['slug'], $c['name']['fr'] ?? $c['name']['en'] ?? '', $c['count']], $categories));

            return self::SUCCESS;
        }

        $category = collect($categories)->firstWhere('slug', $this->argument('category'));

        if ($category === null) {
            $this->error('Unknown category: run the command without argument to list them.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // a dry run writes nothing: it needs no owner
        $user = $dryRun ? new User : User::query()->where('is_admin', true)->when($this->option('user'), fn ($q, string $email) => $q->where('email', $email))->orderBy('created_at')->first();

        if (! $user instanceof User) {
            $this->error('No admin user found to own the imports.');

            return self::FAILURE;
        }

        $result = $import->handle($user, $category['id'], $dryRun);

        if ($dryRun) {
            $this->table(['Parcours', 'km', 'D+ (m)'], $result['preview']);
        }

        foreach ($result['failed'] as $failure) {
            $this->warn($failure);
        }

        $this->info(($dryRun ? count($result['preview']).' would be imported' : "{$result['created']} imported").", {$result['skipped']} skipped (no GPX, unpublished or already imported), ".count($result['failed']).' failed.');

        return self::SUCCESS;
    }
}
