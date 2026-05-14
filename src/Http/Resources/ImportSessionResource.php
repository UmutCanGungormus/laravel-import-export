<?php

namespace Umutcangungormus\LaravelImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Umutcangungormus\LaravelImportExport\Models\ImportSession */
class ImportSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tenant_id' => $this->tenant_id,
            'importable_type' => $this->importable_type,
            'file_name' => $this->file_name,
            'status' => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'successful_rows' => $this->successful_rows,
            'failed_rows' => $this->failed_rows,
            'progress_percentage' => $this->progressPercentage(),
            'detected_headers' => $this->detected_headers,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'mappings' => $this->whenLoaded(
                'columnMappings',
                fn () => ImportColumnMappingResource::collection($this->columnMappings),
            ),
        ];
    }
}
