<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutcangungormus\LaravelImportExport\Data\CreateTemplateData;

class CreateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedModels = array_keys((array) config('import-export.models', []));

        $modelRule = ['required', 'string'];
        if (! empty($allowedModels)) {
            $modelRule[] = \Illuminate\Validation\Rule::in($allowedModels);
        }

        return [
            'model' => $modelRule,
            'template_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_company_wide' => ['sometimes', 'boolean'],
            'template_data' => ['required', 'array'],
            'template_data.mappings' => ['required', 'array', 'min:1'],
            'template_data.mappings.*.source_column' => ['required', 'string'],
            'template_data.mappings.*.target_field' => ['required', 'string'],
        ];
    }

    public function toDto(): CreateTemplateData
    {
        return new CreateTemplateData(
            model_class: $this->validated('model'),
            template_name: $this->validated('template_name'),
            template_data: $this->validated('template_data'),
            description: $this->input('description'),
            is_default: (bool) $this->input('is_default', false),
            is_company_wide: (bool) $this->input('is_company_wide', false),
        );
    }
}
