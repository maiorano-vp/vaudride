<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Password|string>>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'min:2', 'max:40', 'unique:users,nickname'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
