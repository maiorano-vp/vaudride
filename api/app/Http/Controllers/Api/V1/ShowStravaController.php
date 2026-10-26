<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowStravaController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $connection = $user->stravaConnection;

        return response()->json(['connected' => $connection !== null, 'athlete_id' => $connection?->athlete_id]);
    }
}
