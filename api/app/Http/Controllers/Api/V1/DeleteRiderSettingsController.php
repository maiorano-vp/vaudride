<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Erases the player's rider settings (weight included) from the server: the player's right to deletion. */
final readonly class DeleteRiderSettingsController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        $user->update(['rider_settings' => null, 'rider_settings_updated_at' => null]);

        return response()->noContent();
    }
}
