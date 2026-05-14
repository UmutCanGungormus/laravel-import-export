<?php

namespace Umutcangungormus\LaravelImportExport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate */
class ImportMappingTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tenant_id' => $this->tenant_id,
            'importable_type' => $this->importable_type,
            'template_name' => $this->template_name,
            'description' => $this->description,
            'is_default' => $this->is_default,
            'is_company_wide' => $this->is_company_wide,
            'template_data' => $this->template_data,
            'usage_count' => $this->usage_count,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
