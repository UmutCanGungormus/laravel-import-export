<?php

use Illuminate\Database\Eloquent\Builder;
use Umutcangungormus\LaravelImportExport\Services\ImportExportService;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

it('does not reach into auth()->user()->company_id anywhere in package code', function () {
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../src'));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    foreach ($files as $f) {
        $contents = file_get_contents($f);
        expect($contents)
            ->not->toContain('auth()->user()->company_id', "file leaks tenant lookup: {$f}");
    }
});

it('ImportExportService injects TenantResolverContract and uses currentTenantId()', function () {
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

    app()->instance(TenantResolverContract::class, $resolver);

    expect(app(ImportExportService::class)->currentTenantId())->toBe('tenant-42');
});
