<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Stringable;

/** What the rider tells the app about themselves; same fields and limits as the app's lib/rider/settings.ts. */
final class UpdateRiderSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'weightKg' => ['required', 'numeric', 'between:30,200'],
            'bikeKg' => ['required', 'numeric', 'between:5,25'],
            'position' => ['required', Rule::in(['hoods', 'drops', 'aero', 'tops', 'custom'])],
            'cda' => ['required', 'numeric', 'between:0.15,0.6'],
            'ftp' => ['required', 'integer', 'between:50,600'],
            'difficultyPct' => ['required', 'integer', 'between:30,100', 'multiple_of:5'],
        ];
    }
}
