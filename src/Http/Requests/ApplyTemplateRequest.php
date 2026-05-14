<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutcangungormus\LaravelImportExport\Data\ApplyTemplateData;

class ApplyTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer'],
        ];
    }

    public function toDto(): ApplyTemplateData
    {
        return new ApplyTemplateData(
            template_id: (int) $this->validated('template_id'),
        );
    }
}
