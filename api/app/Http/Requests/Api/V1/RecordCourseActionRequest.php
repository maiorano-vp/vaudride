<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CourseActionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Stringable;

final class RecordCourseActionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Stringable|string>>
     */
    public function rules(): array
    {
        return [
            // a person declaring they rode it is counted by the declaration itself, not by this open endpoint
            'action' => ['required', Rule::enum(CourseActionType::class)->except(CourseActionType::DeclaredReal)],
        ];
    }
}
