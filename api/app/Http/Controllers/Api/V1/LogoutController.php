<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class LogoutController
{
    public function __invoke(Request $request): Response
    {
        $request->user()?->currentAccessToken()->delete();

        return response()->noContent();
    }
}
