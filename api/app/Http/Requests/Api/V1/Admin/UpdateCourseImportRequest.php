<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\LicenceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class UpdateCourseImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Enum|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'licence_status' => ['sometimes', Rule::enum(LicenceStatus::class)],
            'course_slug' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'trim_start_m' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'trim_end_m' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reversed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $start = $this->input('trim_start_m');
                $end = $this->input('trim_end_m');
                if (is_numeric($start) && is_numeric($end) && (float) $end <= (float) $start) {
                    $validator->errors()->add('trim_end_m', 'The trim end must be after the trim start.');
                }
            },
        ];
    }
}
