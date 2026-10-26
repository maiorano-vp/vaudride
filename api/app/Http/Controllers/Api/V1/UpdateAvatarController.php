<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateAvatarRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;

final readonly class UpdateAvatarController
{
    public function __invoke(UpdateAvatarRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->update(['avatar' => $request->validated()]);

        return new UserResource($user);
    }
}
