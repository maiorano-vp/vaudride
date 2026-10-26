<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

final readonly class ForgotPasswordController
{
    public function __invoke(ForgotPasswordRequest $request): Response
    {
        Password::sendResetLink(['email' => $request->string('email')->toString()]);

        // same answer whether or not the address exists (no account enumeration)
        return response()->noContent();
    }
}
