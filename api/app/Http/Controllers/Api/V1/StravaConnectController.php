<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\StravaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;

final readonly class StravaConnectController
{
    /**
     * Returns the Strava authorize URL; the signed `state` ties the callback to this user for 10 minutes.
     */
    public function __invoke(Request $request, StravaClient $strava): JsonResponse
    {
        abort_unless($strava->isConfigured(), Response::HTTP_SERVICE_UNAVAILABLE, 'Strava is not configured.');

        $user = $request->user();
        assert($user instanceof User);

        $state = Crypt::encryptString(json_encode(['user' => $user->id, 'exp' => now()->addMinutes(10)->getTimestamp()], JSON_THROW_ON_ERROR));

        return response()->json(['url' => $strava->authorizeUrl($state, route('v1.strava.callback'))]);
    }
}
