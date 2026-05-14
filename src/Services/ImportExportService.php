<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Actions\StartImportAction;
use Umutcangungormus\LaravelImportExport\Actions\UpdateMappingAction;
use Umutcangungormus\LaravelImportExport\Contracts\ColumnMatcherContract;
use Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Data\UpdateMappingData;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Models\ImportColumnMapping;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

/**
 * Application-facade for the entire import/export subsystem.
 *
 * All authorization decisions are intentionally left to the host
 * application — the package only names the gate abilities in
 * `config('import-export.gates.*')` and assumes the caller has already
 * checked them.
 */
class ImportExportService
{
    public function __construct(
        private InitializeImportAction $initializeAction,
        private StartImportAction $startAction,
        private UpdateMappingAction $updateMappingAction,
        private ColumnMatcherContract $columnMatcher,
        private FailureHandlerContract $failureHandler,
        private ModelExportService $exportService,
        private TenantResolverContract $tenantResolver,
    ) {}

    // ── Import Session ────────────────────────────────────────────────────

    public function listSessions(?int $userId = null, ?string $status = null, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ImportSession::query()
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($perPage);
    }

    public function initialize(InitializeImportData $data, ?int $userId = null): ImportSession
    {
        return $this->initializeAction->execute($data, $userId);
    }

    public function show(int $id): ImportSession
    {
        return ImportSession::findOrFail($id);
    }

    public function start(int $id): ImportSession
    {
        $session = ImportSession::findOrFail($id);

        return $this->startAction->execute($session);
    }

    public function cancel(int $id): ImportSession
    {
        $session = ImportSession::findOrFail($id);
        $session->markAs(ImportStatus::Cancelled);

        return $session->fresh();
    }

    // ── Mappings ──────────────────────────────────────────────────────────

    public function listMappings(int $id): \Illuminate\Database\Eloquent\Collection
    {
        return ImportSession::findOrFail($id)->columnMappings;
    }

    public function updateMapping(int $id, UpdateMappingData $data): ImportColumnMapping
    {
        $session = ImportSession::findOrFail($id);

        return $this->updateMappingAction->execute($session, $data);
    }

    public function updateMappingsBatch(int $id, array $dtos): ImportSession
    {
        $session = ImportSession::findOrFail($id);
        $this->updateMappingAction->executeBatch($session, $dtos);

        return $session->fresh();
    }

    public function getMappingSuggestions(int $id, string $sourceColumn): array
    {
        $session = ImportSession::findOrFail($id);
        $modelClass = $session->importable_type;

        if (! method_exists($modelClass, 'getImportableFields')) {
            return [];
        }

        return $this->columnMatcher->suggest($sourceColumn, $modelClass::getImportableFields());
    }

    // ── Progress & Failures ───────────────────────────────────────────────

    public function getProgress(int $id): array
    {
        $session = ImportSession::findOrFail($id);

        return [
            'status' => $session->status,
            'total_rows' => $session->total_rows,
            'processed_rows' => $session->processed_rows,
            'successful_rows' => $session->successful_rows,
            'failed_rows' => $session->failed_rows,
            'progress_percentage' => $session->progressPercentage(),
        ];
    }

    public function getFailuresSummary(int $id): array
    {
        $session = ImportSession::findOrFail($id);

        if (method_exists($this->failureHandler, 'summary')) {
            return $this->failureHandler->summary($session);
        }

        return [
            'total_failures' => $session->failures()->count(),
            'error_types' => [],
        ];
    }

    public function exportFailures(int $id): StreamedResponse
    {
        $session = ImportSession::findOrFail($id);

        return $this->failureHandler->export($session);
    }

    /**
     * @return int|string|null the tenant id for the current request, if any
     */
    public function currentTenantId(): int|string|null
    {
        return $this->tenantResolver->currentTenantId();
    }

    // ── Export ────────────────────────────────────────────────────────────

    public function exportModel(string $modelClass, ?callable $scope = null): StreamedResponse
    {
        return $this->exportService->export($modelClass, $scope);
    }
}
