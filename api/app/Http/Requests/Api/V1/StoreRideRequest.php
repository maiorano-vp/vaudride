<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Stringable;

final class StoreRideRequest extends FormRequest
{
    /**
     * Samples use short keys: t seconds, d metres, p watts, c rpm, h bpm, s km/h.
     *
     * @return array<string, array<int, ValidationRule|Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'course' => ['required', 'string', Rule::exists('courses', 'slug')->where('published', true)],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'elevation_gain_m' => ['required', 'integer', 'min:0', 'max:10000'],
            'samples' => ['required', 'array', 'min:2', 'max:20000'],
            'samples.*.t' => ['required', 'integer', 'min:0'],
            'samples.*.d' => ['required', 'numeric', 'min:0'],
            'samples.*.p' => ['required', 'integer', 'min:0', 'max:3000'],
            'samples.*.c' => ['nullable', 'integer', 'min:0', 'max:300'],
            'samples.*.h' => ['nullable', 'integer', 'min:0', 'max:260'],
            'samples.*.s' => ['required', 'numeric', 'min:0', 'max:120'],
        ];
    }
}
