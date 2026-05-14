<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Illuminate\Support\Collection;
use Umutcangungormus\LaravelImportExport\Actions\ApplyTemplateAction;
use Umutcangungormus\LaravelImportExport\Actions\CreateTemplateAction;
use Umutcangungormus\LaravelImportExport\Actions\SaveTemplateAction;
use Umutcangungormus\LaravelImportExport\Data\CreateTemplateData;
use Umutcangungormus\LaravelImportExport\Data\SaveTemplateData;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

class MappingTemplateService
{
    public function __construct(
        private CreateTemplateAction $createTemplateAction,
        private SaveTemplateAction $saveTemplateAction,
        private ApplyTemplateAction $applyTemplateAction,
        private ImportMappingTemplateService $templateService,
    ) {}

    public function list(?int $userId, ?string $importableType = null, int|string|null $tenantId = null): Collection
    {
        return $this->templateService->getUserTemplates($userId, $importableType, $tenantId);
    }

    public function show(int $id): ImportMappingTemplate
    {
        return ImportMappingTemplate::findOrFail($id);
    }

    public function create(CreateTemplateData $data, ?int $userId, int|string|null $tenantId): ImportMappingTemplate
    {
        return $this->createTemplateAction->execute($data, $userId, $tenantId);
    }

    public function update(int $id, array $data): ImportMappingTemplate
    {
        $template = ImportMappingTemplate::findOrFail($id);

        return $this->templateService->update($template, $data);
    }

    public function delete(int $id): void
    {
        $template = ImportMappingTemplate::findOrFail($id);
        $this->templateService->delete($template);
    }

    public function setDefault(int $id): ImportMappingTemplate
    {
        $template = ImportMappingTemplate::findOrFail($id);

        $template->setAsDefault();

        return $template->fresh();
    }

    public function saveFromSession(int $sessionId, SaveTemplateData $data): ImportMappingTemplate
    {
        $session = ImportSession::findOrFail($sessionId);

        return $this->saveTemplateAction->execute($session, $data);
    }

    public function applyToSession(int $sessionId, int $templateId): ImportSession
    {
        $session = ImportSession::findOrFail($sessionId);
        $template = ImportMappingTemplate::findOrFail($templateId);

        return $this->applyTemplateAction->execute($session, $template);
    }

    public function validate(int $id): array
    {
        $template = ImportMappingTemplate::findOrFail($id);

        return $this->templateService->validateTemplate($template);
    }
}
