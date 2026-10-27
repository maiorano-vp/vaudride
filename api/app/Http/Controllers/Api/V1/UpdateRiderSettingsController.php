<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateRiderSettingsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final readonly class UpdateRiderSettingsController
{
    public function __invoke(UpdateRiderSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $user->update(['rider_settings' => $request->validated(), 'rider_settings_updated_at' => now()]);

        return response()->json([
            'data' => $user->rider_settings,
            'updated_at' => $user->rider_settings_updated_at?->toIso8601String(),
        ]);
    }
}
