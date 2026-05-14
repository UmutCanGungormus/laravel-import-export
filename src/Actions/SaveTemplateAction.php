<?php

namespace Umutcangungormus\LaravelImportExport\Actions;

use Umutcangungormus\LaravelImportExport\Data\SaveTemplateData;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Services\ImportMappingTemplateService;

class SaveTemplateAction
{
    public function __construct(
        private ImportMappingTemplateService $templateService,
    ) {}

    public function execute(ImportSession $session, SaveTemplateData $data): ImportMappingTemplate
    {
        return $this->templateService->createFromSession(
            session: $session,
            templateName: $data->template_name,
            description: $data->description,
            isDefault: $data->is_default,
        );
    }
}
