<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The player's own rider settings (null until they save some); nobody else can read them. */
final readonly class ShowRiderSettingsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json([
            'data' => $user->rider_settings,
            'updated_at' => $user->rider_settings_updated_at?->toIso8601String(),
        ]);
    }
}
