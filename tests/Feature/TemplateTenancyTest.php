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

it('scopes the default flag to the template tenant', function () {
    $make = fn (int|string|null $tenantId, string $name, bool $isDefault) => ImportMappingTemplate::create([
        'user_id' => 7,
        'tenant_id' => $tenantId,
        'importable_type' => FakeImportModel::class,
        'template_name' => $name,
        'is_default' => $isDefault,
        'is_company_wide' => false,
        'template_data' => ['mappings' => [['source_column' => 'SKU', 'target_field' => 'sku']]],
    ]);

    $otherTenant = $make('tenant-b', 'B default', true);
    $untenanted = $make(null, 'No tenant default', true);
    $current = $make('tenant-a', 'A first', true);
    $promoted = $make('tenant-a', 'A second', false);

    $promoted->setAsDefault();

    // Same tenant: the previous default is demoted.
    expect($promoted->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse()
        // Other tenants — including the untenanted row — are untouched.
        ->and($otherTenant->fresh()->is_default)->toBeTrue()
        ->and($untenanted->fresh()->is_default)->toBeTrue();
});

it('scopes the default flag among untenanted templates', function () {
    $make = fn (int|string|null $tenantId, string $name, bool $isDefault) => ImportMappingTemplate::create([
        'user_id' => 9,
        'tenant_id' => $tenantId,
        'importable_type' => FakeImportModel::class,
        'template_name' => $name,
        'is_default' => $isDefault,
        'is_company_wide' => false,
        'template_data' => ['mappings' => [['source_column' => 'SKU', 'target_field' => 'sku']]],
    ]);

    $tenanted = $make('tenant-c', 'C default', true);
    $current = $make(null, 'Null first', true);
    $promoted = $make(null, 'Null second', false);

    $promoted->setAsDefault();

    expect($promoted->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse()
        ->and($tenanted->fresh()->is_default)->toBeTrue();
});
