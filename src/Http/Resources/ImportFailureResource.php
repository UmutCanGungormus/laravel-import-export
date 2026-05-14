<?php

namespace Umutcangungormus\LaravelImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Umutcangungormus\LaravelImportExport\Models\ImportFailure */
class ImportFailureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'import_session_id' => $this->import_session_id,
            'row_number' => $this->row_number,
            'row_data' => $this->row_data,
            'errors' => $this->errors,
            'exception_message' => $this->exception_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
