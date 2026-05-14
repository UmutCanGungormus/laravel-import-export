<?php

namespace Umutcangungormus\LaravelImportExport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;

class InitializeImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:51200'], // 50 MB
            'options.header_row' => ['sometimes', 'integer', 'min:1'],
            'options.chunk_size' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ];
    }

    public function toDto(int|string|null $tenantId = null): InitializeImportData
    {
        $file = $this->file('file');
        $disk = (string) config('import-export.disk', 'local');
        $path = $file->store((string) config('import-export.storage_path', 'imports'), $disk);

        return new InitializeImportData(
            model_class: $this->validated('model'),
            file_path: $path,
            file_name: $file->getClientOriginalName(),
            file_disk: $disk,
            tenant_id: $tenantId,
            header_row: (int) ($this->input('options.header_row', 1)),
            chunk_size: (int) ($this->input('options.chunk_size', config('import-export.chunk_size', 1000))),
        );
    }
}
