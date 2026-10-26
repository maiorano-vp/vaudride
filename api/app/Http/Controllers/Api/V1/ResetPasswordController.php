<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final readonly class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request): Response
    {
        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }

        return response()->noContent();
    }
}
