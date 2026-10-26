<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Stringable;

/** The kit of the 3D cyclist: five colours and three style variants (see the app's lib/kit/kit.ts). */
final class UpdateAvatarRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Stringable|string>>
     */
    public function rules(): array
    {
        $color = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'jersey' => $color,
            'accent' => $color,
            'shorts' => $color,
            'helmet' => $color,
            'bike' => $color,
            'jerseyStyle' => ['required', Rule::in(['solid', 'stripes', 'band'])],
            'helmetStyle' => ['required', Rule::in(['road', 'aero', 'cap'])],
            'wheels' => ['required', Rule::in(['spoked', 'disc'])],
        ];
    }
}
