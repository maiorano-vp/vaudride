<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ImportSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCourseImportRequest extends FormRequest
{
    /**
     * The GPX arrives either as text (`gpx`, used by the browser extension) or as an uploaded `file`.
     *
     * @return array<string, array<int, ValidationRule|Enum|string>>
     */
    public function rules(): array
    {
        return [
            'gpx' => ['required_without:file', 'string', 'max:10000000'],
            'file' => ['required_without:gpx', 'file', 'max:10240'],
            'source' => ['nullable', Rule::enum(ImportSource::class)],
            'source_url' => ['nullable', 'url:https', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
        ];
    }
}
