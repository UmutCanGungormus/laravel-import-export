<?php

use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Services\ImportMappingTemplateService;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportModel;

/**
 * Covers `is_default`: which templates a promotion demotes (tenant scope), and
 * that an update can clear the flag again.
 */
function makeTemplate(int $userId, int|string|null $tenantId, string $name, bool $isDefault): ImportMappingTemplate
{
    return ImportMappingTemplate::create([
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'importable_type' => FakeImportModel::class,
        'template_name' => $name,
        'is_default' => $isDefault,
        'is_company_wide' => false,
        'template_data' => ['mappings' => [['source_column' => 'SKU', 'target_field' => 'sku']]],
    ]);
}

it('scopes the default flag to the template tenant', function () {
    $otherTenant = makeTemplate(7, 'tenant-b', 'B default', true);
    $untenanted = makeTemplate(7, null, 'No tenant default', true);
    $current = makeTemplate(7, 'tenant-a', 'A first', true);
    $promoted = makeTemplate(7, 'tenant-a', 'A second', false);

    $promoted->setAsDefault();

    // Same tenant: the previous default is demoted.
    expect($promoted->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse()
        // Other tenants — including the untenanted row — are untouched.
        ->and($otherTenant->fresh()->is_default)->toBeTrue()
        ->and($untenanted->fresh()->is_default)->toBeTrue();
});

it('scopes the default flag among untenanted templates', function () {
    $tenanted = makeTemplate(9, 'tenant-c', 'C default', true);
    $current = makeTemplate(9, null, 'Null first', true);
    $promoted = makeTemplate(9, null, 'Null second', false);

    $promoted->setAsDefault();

    expect($promoted->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse()
        ->and($tenanted->fresh()->is_default)->toBeTrue();
});

it('clears the default flag when update is given is_default false', function () {
    $service = app(ImportMappingTemplateService::class);
    $template = makeTemplate(11, 'tenant-d', 'Toggle me', true);

    expect($service->update($template, ['is_default' => false])->is_default)->toBeFalse()
        ->and($service->update($template, ['is_default' => true])->is_default)->toBeTrue()
        // An absent key leaves the flag alone.
        ->and($service->update($template, ['template_name' => 'Renamed'])->is_default)->toBeTrue();
});

it('leaves the default flag alone when update is given a null is_default', function () {
    $service = app(ImportMappingTemplateService::class);
    $template = makeTemplate(13, 'tenant-e', 'Untouched', true);

    // The bundled UpdateTemplateRequest sends null for "key not in the body".
    expect($service->update($template, ['is_default' => null])->is_default)->toBeTrue();
});
