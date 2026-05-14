<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutcangungormus\LaravelImportExport\Data\SaveTemplateData;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): SaveTemplateData
    {
        return new SaveTemplateData(
            template_name: $this->validated('template_name'),
            description: $this->input('description'),
            is_default: (bool) $this->input('is_default', false),
        );
    }
}
