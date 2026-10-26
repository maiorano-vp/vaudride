<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:make-admin {email : Email of an existing user}')]
#[Description('Gives a user access to the admin endpoints (course imports)')]
final class MakeAdminCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user instanceof User) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);
        $this->info($user->email.' is now an admin.');

        return self::SUCCESS;
    }
}
