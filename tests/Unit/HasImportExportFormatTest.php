<?php

use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportModel;

/**
 * Regression for I3: HasImportExport::formatExportValue was calling
 * __('import-export::messages.export.yes') / .no, but the lang files publish
 * the keys under export.yes / export.no (no "messages." prefix). The previous
 * code rendered the literal key string as the cell value.
 */
beforeEach(function () {
    config()->set('import-export.models.'.FakeImportModel::class, [
        'export_fields' => [
            'is_active' => [
                'accessor' => 'is_active',
                'format' => 'boolean',
            ],
        ],
    ]);
});

it('registers the import-export translation namespace at boot', function () {
    // Sanity check: without the namespace being loaded by the service
    // provider, the broken-key fix below would have nothing to test against.
    app()->setLocale('en');
    expect(__('import-export::export.yes'))->toBe('Yes');
});

it('renders boolean exports using the namespaced "Yes" lang key for true', function () {
    app()->setLocale('en');
    $model = new FakeImportModel(['is_active' => true]);

    $row = FakeImportModel::transformForExport($model);

    expect($row['is_active'])->toBe('Yes');
});

it('renders boolean exports using the namespaced "No" lang key for false', function () {
    app()->setLocale('en');
    $model = new FakeImportModel(['is_active' => false]);

    $row = FakeImportModel::transformForExport($model);

    expect($row['is_active'])->toBe('No');
});

it('does not leak the literal lang key when the locale is Turkish', function () {
    app()->setLocale('tr');
    $model = new FakeImportModel(['is_active' => true]);

    $row = FakeImportModel::transformForExport($model);

    expect($row['is_active'])
        ->not->toContain('messages.export.yes')
        ->and($row['is_active'])->not->toContain('import-export::');
});
