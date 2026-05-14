<?php

use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportModel;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportProcessor;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/sample.csv', file_get_contents(__DIR__.'/../Fixtures/sample.csv'));

    $this->app['db']->connection()->getSchemaBuilder()->create('fake_import_items', function ($t) {
        $t->id();
        $t->string('sku');
        $t->string('name');
        $t->decimal('price', 8, 2)->nullable();
        $t->timestamps();
    });

    config()->set('import-export.models.'.FakeImportModel::class, [
        'processor' => FakeImportProcessor::class,
        'unique_by' => ['sku'],
        'fields' => [
            'sku' => ['required' => true, 'type' => 'string', 'validation' => ['required', 'string', 'max:64']],
            'name' => ['required' => true, 'type' => 'string', 'validation' => ['required', 'string', 'max:255']],
            'price' => ['required' => false, 'type' => 'decimal', 'validation' => ['nullable', 'numeric']],
        ],
    ]);

    FakeImportProcessor::reset();
});

it('runs the full pipeline (detect headers, auto-match, default template) and lands the session in Mapping', function () {
    $data = new InitializeImportData(
        model_class: FakeImportModel::class,
        file_path: 'imports/sample.csv',
        file_name: 'sample.csv',
        file_disk: 'local',
        tenant_id: null,
        header_row: 1,
        chunk_size: 100,
    );

    $session = app(InitializeImportAction::class)->execute($data, userId: null);

    expect($session->status)->toBe(ImportStatus::Mapping);
    expect($session->detected_headers)->toBe(['sku', 'name', 'price']);
    expect($session->total_rows)->toBe(3);

    // Three auto-matches, all confirmed at 1.0 confidence (exact key match).
    $mappings = $session->columnMappings;
    expect($mappings)->toHaveCount(3);
    foreach ($mappings as $m) {
        expect($m->is_confirmed)->toBeTrue();
        expect($m->confidence_score)->toBe(1.0);
    }
});
