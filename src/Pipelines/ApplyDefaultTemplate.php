<?php

namespace Umutcangungormus\LaravelImportExport\Pipelines;

use Closure;
use Umutcangungormus\LaravelImportExport\Services\ImportMappingTemplateService;

/**
 * If the user has a default template for this model, apply it to the session.
 * Runs AFTER AutoMatchColumns so template mappings overwrite auto-matches
 * with higher confidence (1.0) and mark them as confirmed.
 */
class ApplyDefaultTemplate
{
    public function __construct(
        private ImportMappingTemplateService $templateService,
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        if (! config('import-export.templates.auto_apply_default', true)) {
            return $next($payload);
        }

        $session = $payload['session'];

        $defaultTemplate = $this->templateService->getDefaultTemplate(
            userId: $session->user_id,
            importableType: $session->importable_type,
            tenantId: $session->tenant_id,
        );

        if ($defaultTemplate) {
            $validation = $this->templateService->validateTemplate($defaultTemplate);

            if ($validation['valid']) {
                $this->templateService->applyToSession($defaultTemplate, $session);
                $payload['applied_template'] = $defaultTemplate;
            }
        }

        return $next($payload);
    }
}
