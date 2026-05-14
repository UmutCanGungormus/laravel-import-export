<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;
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

it('persists the tenant id from a custom TenantResolverContract onto the ImportSession', function () {
    $resolver = new class implements TenantResolverContract
    {
        public function currentTenantId(): int|string|null
        {
            return 'tenant-77';
        }

        public function scopeQuery(Builder $query): Builder
        {
            return $query;
        }
    };

    $this->app->instance(TenantResolverContract::class, $resolver);

    $data = new InitializeImportData(
        model_class: FakeImportModel::class,
        file_path: 'imports/sample.csv',
        file_name: 'sample.csv',
        file_disk: 'local',
        tenant_id: null, // ← intentionally null to force resolver lookup
        header_row: 1,
        chunk_size: 100,
    );

    $session = app(InitializeImportAction::class)->execute($data, userId: null);

    expect($session->tenant_id)->toBe('tenant-77');
});

it('falls back to NullTenantResolver returning null when the host has no tenancy', function () {
    $resolver = app(TenantResolverContract::class);

    expect($resolver)->toBeInstanceOf(\Umutcangungormus\LaravelImportExport\Tenancy\NullTenantResolver::class);
    expect($resolver->currentTenantId())->toBeNull();
});
