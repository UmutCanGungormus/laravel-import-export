<?php

use Illuminate\Database\Eloquent\Builder;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportModel;

/**
 * Regression for I6: ImportTemplateController::store passed `tenantId: null`
 * to MappingTemplateService::create, so every template created via HTTP had
 * a null tenant_id regardless of the bound TenantResolverContract. The
 * sister controller (ImportSessionController) correctly forwards
 * currentTenantId() — the template controller now does the same.
 */
beforeEach(function () {
    config()->set('import-export.routes.enabled', true);

    // Re-boot the provider so the routes file is re-registered against the
    // current Route facade.
    $provider = new \Umutcangungormus\LaravelImportExport\ImportExportServiceProvider($this->app);
    $provider->boot();

    config()->set('import-export.models.'.FakeImportModel::class, [
        'fields' => [
            'sku' => ['required' => true, 'type' => 'string'],
            'name' => ['required' => true, 'type' => 'string'],
        ],
    ]);
});

it('persists the resolver tenant id on templates created via the HTTP layer', function () {
    $resolver = new class implements TenantResolverContract
    {
        public function currentTenantId(): int|string|null
        {
            return 'tenant-42';
        }

        public function scopeQuery(Builder $query): Builder
        {
            return $query;
        }
    };

    $this->app->instance(TenantResolverContract::class, $resolver);

    $response = $this->postJson('/api/import-export/templates', [
        'model' => FakeImportModel::class,
        'template_name' => 'Default mapping',
        'template_data' => [
            'mappings' => [
                ['source_column' => 'SKU', 'target_field' => 'sku'],
                ['source_column' => 'Name', 'target_field' => 'name'],
            ],
        ],
    ]);

    $response->assertCreated();

    $template = ImportMappingTemplate::query()->where('template_name', 'Default mapping')->firstOrFail();
    expect($template->tenant_id)->toBe('tenant-42');
});

it('persists null tenant_id when the host has no tenancy bound (NullTenantResolver)', function () {
    $response = $this->postJson('/api/import-export/templates', [
        'model' => FakeImportModel::class,
        'template_name' => 'Untenanted',
        'template_data' => [
            'mappings' => [
                ['source_column' => 'SKU', 'target_field' => 'sku'],
            ],
        ],
    ]);

    $response->assertCreated();

    $template = ImportMappingTemplate::query()->where('template_name', 'Untenanted')->firstOrFail();
    expect($template->tenant_id)->toBeNull();
});
