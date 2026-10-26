<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\UploadRideToStrava;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class UploadRideToStravaController
{
    public function __invoke(Request $request, string $ride): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $model = $user->rides()->findOrFail($ride);

        abort_unless($user->stravaConnection()->exists(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Strava is not connected.');
        abort_if($model->strava_activity_id !== null, Response::HTTP_CONFLICT, 'This ride is already on Strava.');

        // a failed earlier upload is retried from scratch
        if ($model->strava_error !== null) {
            $model->update(['strava_error' => null, 'strava_upload_id' => null]);
        }

        UploadRideToStrava::dispatch($model);

        return response()->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
