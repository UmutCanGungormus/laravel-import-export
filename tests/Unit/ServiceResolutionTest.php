<?php

use Umutcangungormus\LaravelImportExport\Actions\ApplyTemplateAction;
use Umutcangungormus\LaravelImportExport\Actions\CreateTemplateAction;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Actions\SaveTemplateAction;
use Umutcangungormus\LaravelImportExport\Actions\StartImportAction;
use Umutcangungormus\LaravelImportExport\Actions\UpdateMappingAction;
use Umutcangungormus\LaravelImportExport\Contracts\ColumnMatcherContract;
use Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract;
use Umutcangungormus\LaravelImportExport\Jobs\ProcessImportJob;
use Umutcangungormus\LaravelImportExport\Pipelines\ApplyDefaultTemplate;
use Umutcangungormus\LaravelImportExport\Pipelines\AutoMatchColumns;
use Umutcangungormus\LaravelImportExport\Pipelines\DetectFileHeaders;
use Umutcangungormus\LaravelImportExport\Pipelines\ValidateRequiredMappings;
use Umutcangungormus\LaravelImportExport\Services\ColumnMatcherService;
use Umutcangungormus\LaravelImportExport\Services\FailureHandlerService;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;
use Umutcangungormus\LaravelImportExport\Services\ImportExportService;
use Umutcangungormus\LaravelImportExport\Services\ImportMappingTemplateService;
use Umutcangungormus\LaravelImportExport\Services\MappingTemplateService;
use Umutcangungormus\LaravelImportExport\Services\ModelExportService;
use Umutcangungormus\LaravelImportExport\Tenancy\NullTenantResolver;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

it('autoloads every service, pipeline, action and job class', function () {
    $classes = [
        ImportExportService::class,
        ColumnMatcherService::class,
        ModelExportService::class,
        FileReaderService::class,
        MappingTemplateService::class,
        FailureHandlerService::class,
        ImportMappingTemplateService::class,
        DetectFileHeaders::class,
        AutoMatchColumns::class,
        ValidateRequiredMappings::class,
        ApplyDefaultTemplate::class,
        InitializeImportAction::class,
        StartImportAction::class,
        CreateTemplateAction::class,
        SaveTemplateAction::class,
        UpdateMappingAction::class,
        ApplyTemplateAction::class,
        ProcessImportJob::class,
    ];

    foreach ($classes as $c) {
        expect(class_exists($c))->toBeTrue("class {$c} not autoloadable");
    }
});

it('resolves the ImportExportService with all dependencies wired', function () {
    $service = app(ImportExportService::class);

    expect($service)->toBeInstanceOf(ImportExportService::class);
});

it('binds ColumnMatcherContract to ColumnMatcherService', function () {
    expect(app(ColumnMatcherContract::class))->toBeInstanceOf(ColumnMatcherService::class);
});

it('binds FailureHandlerContract to FailureHandlerService', function () {
    expect(app(FailureHandlerContract::class))->toBeInstanceOf(FailureHandlerService::class);
});

it('binds TenantResolverContract to NullTenantResolver by default', function () {
    $resolver = app(TenantResolverContract::class);

    expect($resolver)->toBeInstanceOf(NullTenantResolver::class);
    expect($resolver->currentTenantId())->toBeNull();
});

it('every pipeline pipe exposes the handle($payload, Closure $next) signature', function () {
    foreach ([DetectFileHeaders::class, AutoMatchColumns::class, ValidateRequiredMappings::class, ApplyDefaultTemplate::class] as $pipe) {
        $reflection = new ReflectionMethod($pipe, 'handle');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2, "{$pipe}::handle must take exactly two parameters");
        expect((string) $params[1]->getType())->toBe(Closure::class);
    }
});

it('process import job implements ShouldQueue and only depends on Illuminate queue contracts', function () {
    $contract = Illuminate\Contracts\Queue\ShouldQueue::class;
    expect((new ReflectionClass(ProcessImportJob::class))->implementsInterface($contract))->toBeTrue();

    $contents = file_get_contents((new ReflectionClass(ProcessImportJob::class))->getFileName());
    expect($contents)
        ->not->toContain('Laravel\\Horizon')
        ->not->toContain('use App\\');
});
