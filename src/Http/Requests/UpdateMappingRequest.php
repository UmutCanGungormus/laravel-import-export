<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutcangungormus\LaravelImportExport\Data\UpdateMappingData;

class UpdateMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_column' => ['required', 'string'],
            'target_field' => ['nullable', 'string'],
            'confirmed' => ['required', 'boolean'],
        ];
    }

    public function toDto(): UpdateMappingData
    {
        return new UpdateMappingData(
            source_column: $this->validated('source_column'),
            target_field: $this->validated('target_field'),
            confirmed: (bool) $this->validated('confirmed'),
        );
    }
}
