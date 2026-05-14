<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutcangungormus\LaravelImportExport\Data\UpdateTemplateData;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_company_wide' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): UpdateTemplateData
    {
        return new UpdateTemplateData(
            template_name: $this->input('template_name'),
            description: $this->input('description'),
            is_default: $this->has('is_default') ? (bool) $this->input('is_default') : null,
            is_company_wide: $this->has('is_company_wide') ? (bool) $this->input('is_company_wide') : null,
        );
    }
}
