<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array{id: string, nickname: string, email: string, avatar: array<string, string>|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'avatar' => $this->avatar,
        ];
    }
}
