<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\IssueApiToken;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class RegisterController
{
    public function __invoke(RegisterRequest $request, IssueApiToken $issueApiToken): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        return response()->json([
            'token' => $issueApiToken->handle($user, $request->string('device_name', 'app')->toString()),
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }
}
