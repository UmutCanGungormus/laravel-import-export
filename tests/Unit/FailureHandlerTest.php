<?php

use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Models\ImportFailure;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Services\FailureHandlerService;

it('persists a failure row and increments the session failed_rows counter', function () {
    $session = ImportSession::create([
        'user_id' => null,
        'importable_type' => 'App\\Models\\Fake',
        'file_name' => 'a.csv',
        'file_path' => 'a.csv',
        'file_disk' => 'local',
        'status' => ImportStatus::Mapping,
        'total_rows' => 3,
    ]);

    $handler = new FailureHandlerService;
    $handler->record($session, 2, ['a' => 1], ['name required'], null);

    expect(ImportFailure::count())->toBe(1);
    expect($session->fresh()->failed_rows)->toBe(1);

    $row = ImportFailure::firstOrFail();
    expect($row->row_number)->toBe(2);
    expect($row->errors)->toBe(['name required']);
});

it('produces a summary that buckets failures by exception vs validation', function () {
    $session = ImportSession::create([
        'user_id' => null,
        'importable_type' => 'App\\Models\\Fake',
        'file_name' => 'a.csv',
        'file_path' => 'a.csv',
        'file_disk' => 'local',
        'status' => ImportStatus::Mapping,
    ]);

    $handler = new FailureHandlerService;
    $handler->record($session, 1, ['x' => 1], ['validation error']);
    $handler->record($session, 2, ['x' => 2], [], 'PDO crash');

    $summary = $handler->summary($session);

    expect($summary['total_failures'])->toBe(2);
    expect($summary['error_types'])->toHaveKey('validation', 1);
    expect($summary['error_types'])->toHaveKey('exception', 1);
});
