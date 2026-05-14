<?php

use Umutcangungormus\LaravelImportExport\Actions\StartImportAction;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Exceptions\ProcessorNotRegistered;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

it('throws ProcessorNotRegistered when no processor is wired for the importable type', function () {
    $session = ImportSession::create([
        'user_id' => null,
        'importable_type' => 'App\\Models\\NotRegistered',
        'file_name' => 'a.csv',
        'file_path' => 'a.csv',
        'file_disk' => 'local',
        'status' => ImportStatus::Mapping,
    ]);

    $action = app(StartImportAction::class);

    $action->execute($session);
})->throws(ProcessorNotRegistered::class);
