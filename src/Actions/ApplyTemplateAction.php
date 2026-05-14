<?php

namespace Umutcangungormus\LaravelImportExport\Actions;

use Illuminate\Validation\ValidationException;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Services\ImportMappingTemplateService;

class ApplyTemplateAction
{
    public function __construct(
        private ImportMappingTemplateService $templateService,
    ) {}

    public function execute(ImportSession $session, ImportMappingTemplate $template): ImportSession
    {
        if (config('import-export.templates.validate_before_apply', true)) {
            $validation = $this->templateService->validateTemplate($template);

            if (! $validation['valid']) {
                throw ValidationException::withMessages([
                    'template' => $validation['errors'],
                ]);
            }
        }

        $this->templateService->applyToSession($template, $session);

        return $session->fresh(['columnMappings']);
    }
}
