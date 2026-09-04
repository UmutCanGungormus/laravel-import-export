<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Umutcangungormus\LaravelImportExport\Data\UpdateMappingData;
use Umutcangungormus\LaravelImportExport\Enums\MultiColumnStrategy;

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
            'multi_strategy' => ['nullable', 'string', Rule::enum(MultiColumnStrategy::class)],
        ];
    }

    public function toDto(): UpdateMappingData
    {
        return new UpdateMappingData(
            source_column: $this->validated('source_column'),
            target_field: $this->validated('target_field'),
            confirmed: (bool) $this->validated('confirmed'),
            multi_strategy: $this->validated('multi_strategy'),
        );
    }
}
