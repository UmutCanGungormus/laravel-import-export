<?php

namespace Umutcangungormus\LaravelImportExport\Actions;

use Umutcangungormus\LaravelImportExport\Data\CreateTemplateData;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;

class CreateTemplateAction
{
    public function execute(
        CreateTemplateData $data,
        ?int $userId,
        int|string|null $tenantId,
    ): ImportMappingTemplate {
        $template = ImportMappingTemplate::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'importable_type' => $data->model_class,
            'template_name' => $data->template_name,
            'description' => $data->description,
            'is_default' => false,
            'is_company_wide' => $data->is_company_wide,
            'template_data' => $data->template_data,
        ]);

        if ($data->is_default) {
            $template->setAsDefault();
        }

        return $template->fresh();
    }
}
