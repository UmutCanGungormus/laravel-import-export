<?php

use Umutcangungormus\LaravelImportExport\Enums\MatchMethod;
use Umutcangungormus\LaravelImportExport\Services\ColumnMatcherService;

it('matches exact field names with confidence 1.0', function () {
    $matcher = new ColumnMatcherService;
    $fields = [
        'sku' => ['label' => 'SKU', 'aliases' => []],
        'name' => ['label' => 'Name', 'aliases' => []],
    ];

    $results = $matcher->match(['sku', 'name'], $fields);

    expect($results[0]['target_field'])->toBe('sku');
    expect($results[0]['confidence_score'])->toBe(1.0);
    expect($results[0]['match_method'])->toBe(MatchMethod::Exact->value);
});

it('matches aliases at confidence 0.9', function () {
    $matcher = new ColumnMatcherService;
    $fields = [
        'name' => ['label' => 'Customer', 'aliases' => ['employee_name']],
    ];

    $results = $matcher->match(['employee_name'], $fields);

    expect($results[0]['target_field'])->toBe('name');
    expect($results[0]['confidence_score'])->toBe(0.9);
    expect($results[0]['match_method'])->toBe(MatchMethod::Alias->value);
});

it('matches labels at confidence 0.95 before falling through to fuzzy', function () {
    $matcher = new ColumnMatcherService;
    $fields = [
        'sku' => ['label' => 'Product Code', 'aliases' => []],
    ];

    $results = $matcher->match(['Product Code'], $fields);

    expect($results[0]['target_field'])->toBe('sku');
    expect($results[0]['confidence_score'])->toBe(0.95);
    expect($results[0]['match_method'])->toBe(MatchMethod::Label->value);
});

it('returns ranked suggestions for an unknown column', function () {
    $matcher = new ColumnMatcherService;
    $fields = [
        'sku' => ['label' => 'SKU', 'aliases' => ['code']],
        'name' => ['label' => 'Name', 'aliases' => []],
    ];

    $suggestions = $matcher->suggest('skuu', $fields);

    expect($suggestions)->not->toBeEmpty();
    expect($suggestions[0]['field'])->toBe('sku');
});
