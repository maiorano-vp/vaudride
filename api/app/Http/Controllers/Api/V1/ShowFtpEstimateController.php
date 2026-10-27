<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\EstimateFtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** An FTP the app can suggest to the player, from their own rides (null while they have no 20-minute effort). */
final readonly class ShowFtpEstimateController
{
    public function __invoke(Request $request, EstimateFtp $estimate): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json(['data' => $estimate->handle($user)]);
    }
}
