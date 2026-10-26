<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\IssueApiToken;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class LoginController
{
    public function __invoke(LoginRequest $request, IssueApiToken $issueApiToken): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        return response()->json([
            'token' => $issueApiToken->handle($user, $request->string('device_name', 'app')->toString()),
            'user' => new UserResource($user),
        ]);
    }
}
