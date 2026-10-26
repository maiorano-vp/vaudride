<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class IssueApiToken
{
    /**
     * Creates a Sanctum personal access token and returns the plain-text value (shown once).
     */
    public function handle(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
