<?php

namespace Umutcangungormus\LaravelImportExport\Actions;

use Illuminate\Pipeline\Pipeline;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Pipelines\ApplyDefaultTemplate;
use Umutcangungormus\LaravelImportExport\Pipelines\AutoMatchColumns;
use Umutcangungormus\LaravelImportExport\Pipelines\DetectFileHeaders;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

class InitializeImportAction
{
    public function __construct(
        private Pipeline $pipeline,
        private TenantResolverContract $tenantResolver,
    ) {}

    public function execute(InitializeImportData $data, ?int $userId = null): ImportSession
    {
        // DTO can override the tenant id; otherwise pull from the resolver.
        $tenantId = $data->tenant_id ?? $this->tenantResolver->currentTenantId();

        $session = ImportSession::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'importable_type' => $data->model_class,
            'file_name' => $data->file_name,
            'file_path' => $data->file_path,
            'file_disk' => $data->file_disk,
            'status' => ImportStatus::Pending,
            'options' => [
                'header_row' => $data->header_row,
                'chunk_size' => $data->chunk_size,
            ],
        ]);

        $this->pipeline
            ->send(['session' => $session, 'data' => $data])
            ->through([
                DetectFileHeaders::class,
                AutoMatchColumns::class,
                ApplyDefaultTemplate::class,
            ])
            ->thenReturn();

        return $session->fresh(['columnMappings']);
    }
}
