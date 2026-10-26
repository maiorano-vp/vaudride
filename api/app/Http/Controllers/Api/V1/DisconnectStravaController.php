<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\StravaClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class DisconnectStravaController
{
    public function __invoke(Request $request, StravaClient $strava): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        $connection = $user->stravaConnection;

        if ($connection !== null) {
            $strava->deauthorize($connection->access_token);
            $connection->delete();
        }

        return response()->noContent();
    }
}
