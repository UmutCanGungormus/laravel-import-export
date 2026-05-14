<?php

namespace Umutcangungormus\LaravelImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Umutcangungormus\LaravelImportExport\Models\ImportColumnMapping */
class ImportColumnMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_column' => $this->source_column,
            'target_field' => $this->target_field,
            'confidence_score' => $this->confidence_score,
            'match_method' => $this->match_method instanceof \BackedEnum
                ? $this->match_method->value
                : $this->match_method,
            'is_required' => $this->is_required,
            'is_confirmed' => $this->is_confirmed,
        ];
    }
}
