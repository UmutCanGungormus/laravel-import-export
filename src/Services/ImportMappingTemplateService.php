<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Illuminate\Support\Collection;
use Umutcangungormus\LaravelImportExport\Enums\MatchMethod;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

class ImportMappingTemplateService
{
    public function __construct(
        private TenantResolverContract $tenantResolver,
    ) {}

    /**
     * Get all templates visible to the given user (+ tenant-wide templates).
     */
    public function getUserTemplates(
        ?int $userId,
        ?string $importableType = null,
        int|string|null $tenantId = null,
    ): Collection {
        $tenantId = $tenantId ?? $this->tenantResolver->currentTenantId();

        $query = ImportMappingTemplate::query()
            ->where(function ($q) use ($userId, $tenantId) {
                if ($userId !== null) {
                    $q->where('user_id', $userId);
                }

                $q->orWhere(function ($q2) use ($tenantId) {
                    if ($tenantId !== null) {
                        $q2->where('tenant_id', (string) $tenantId)
                            ->where('is_company_wide', true);
                    }
                });
            });

        if ($importableType) {
            $query->where('importable_type', $importableType);
        }

        return $query
            ->orderByDesc('is_default')
            ->orderByDesc('usage_count')
            ->get();
    }

    /**
     * Get the user's default template for a model, or null if none.
     */
    public function getDefaultTemplate(
        ?int $userId,
        string $importableType,
        int|string|null $tenantId = null,
    ): ?ImportMappingTemplate {
        $tenantId = $tenantId ?? $this->tenantResolver->currentTenantId();

        if ($userId !== null) {
            $template = ImportMappingTemplate::query()
                ->where('user_id', $userId)
                ->where('importable_type', $importableType)
                ->where('is_default', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($tenantId !== null) {
            return ImportMappingTemplate::query()
                ->where('tenant_id', (string) $tenantId)
                ->where('importable_type', $importableType)
                ->where('is_company_wide', true)
                ->where('is_default', true)
                ->first();
        }

        return null;
    }

    /**
     * Build a new template from a completed/mapped import session.
     */
    public function createFromSession(
        ImportSession $session,
        string $templateName,
        ?string $description = null,
        bool $isDefault = false,
    ): ImportMappingTemplate {
        $mappings = $session->confirmedMappings()
            ->get()
            ->map(fn ($m) => [
                'source_column' => $m->source_column,
                'target_field' => $m->target_field,
                'confidence_score' => $m->confidence_score,
                'match_method' => is_object($m->match_method) ? $m->match_method->value : $m->match_method,
            ])
            ->values()
            ->all();

        $template = ImportMappingTemplate::create([
            'user_id' => $session->user_id,
            'tenant_id' => $session->tenant_id,
            'importable_type' => $session->importable_type,
            'template_name' => $templateName,
            'description' => $description,
            'is_default' => false,
            'is_company_wide' => false,
            'template_data' => [
                'mappings' => $mappings,
                'metadata' => [
                    'created_from_session_id' => $session->id,
                    'original_file_name' => $session->file_name,
                    'total_mappings' => count($mappings),
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        if ($isDefault) {
            $template->setAsDefault();
        }

        return $template->fresh();
    }

    /**
     * Apply a template's mappings to an import session.
     * Only applies mappings whose source_column exists in the detected headers.
     */
    public function applyToSession(ImportMappingTemplate $template, ImportSession $session): void
    {
        $templateMappings = $template->template_data['mappings'] ?? [];
        $detectedHeaders = $session->detected_headers ?? [];

        foreach ($templateMappings as $mapping) {
            $sourceColumn = $mapping['source_column'];

            if (! in_array($sourceColumn, $detectedHeaders, true)) {
                continue;
            }

            $session->columnMappings()->updateOrCreate(
                ['source_column' => $sourceColumn],
                [
                    'target_field' => $mapping['target_field'],
                    'confidence_score' => 1.0,
                    'match_method' => MatchMethod::Template->value,
                    'is_confirmed' => true,
                    'is_required' => $this->isFieldRequired($session->importable_type, $mapping['target_field']),
                ],
            );
        }

        $template->markAsUsed();
    }

    public function update(ImportMappingTemplate $template, array $data): ImportMappingTemplate
    {
        $fillable = array_filter([
            'template_name' => $data['template_name'] ?? null,
            'description' => $data['description'] ?? null,
            'is_company_wide' => $data['is_company_wide'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($fillable)) {
            $template->update($fillable);
        }

        if (! empty($data['is_default'])) {
            $template->setAsDefault();
        }

        return $template->fresh();
    }

    public function validateTemplate(ImportMappingTemplate $template): array
    {
        $modelClass = $template->importable_type;

        if (! class_exists($modelClass) || ! method_exists($modelClass, 'getImportableFields')) {
            return [
                'valid' => false,
                'errors' => ['Model no longer supports import or does not exist.'],
                'invalid_fields' => [],
            ];
        }

        $currentFields = array_keys($modelClass::getImportableFields());
        $templateFields = collect($template->template_data['mappings'] ?? [])
            ->pluck('target_field')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $invalidFields = array_values(array_diff($templateFields, $currentFields));

        return [
            'valid' => empty($invalidFields),
            'errors' => $invalidFields
                ? ['The following fields no longer exist on the model: '.implode(', ', $invalidFields)]
                : [],
            'invalid_fields' => $invalidFields,
        ];
    }

    public function delete(ImportMappingTemplate $template): void
    {
        $template->delete();
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function isFieldRequired(string $modelClass, ?string $field): bool
    {
        if (! $field || ! method_exists($modelClass, 'getImportableFields')) {
            return false;
        }

        $fields = $modelClass::getImportableFields();

        return (bool) ($fields[$field]['required'] ?? false);
    }
}
