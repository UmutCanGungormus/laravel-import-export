<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Models\ImportMappingTemplate;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportModel;

/*
 * Hosts whose users have uuid / ulid / string primary keys set `user_key_type`;
 * the sessions and templates tables then store `user_id` with that type.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/sample.csv', file_get_contents(__DIR__.'/../Fixtures/sample.csv'));
    config()->set('import-export.models.'.FakeImportModel::class, [
        'unique_by' => ['sku'],
        'fields' => ['sku' => ['required' => true, 'type' => 'string', 'validation' => ['required', 'string']]],
    ]);
});

it('stores a uuid user id on sessions and templates when user_key_type is uuid', function () {
    // Re-create the tables with the uuid key type.
    Schema::dropIfExists(config('import-export.tables.failures', 'import_failures'));
    Schema::dropIfExists(config('import-export.tables.column_mappings', 'import_column_mappings'));
    Schema::dropIfExists(config('import-export.tables.mapping_templates', 'import_mapping_templates'));
    Schema::dropIfExists(config('import-export.tables.sessions', 'import_sessions'));
    config()->set('import-export.user_key_type', 'uuid');
    foreach (glob(__DIR__.'/../../database/migrations/*.php') as $file) {
        (require $file)->up();
    }

    $userId = (string) Str::uuid();
    $session = app(InitializeImportAction::class)->execute(new InitializeImportData(
        model_class: FakeImportModel::class, file_path: 'imports/sample.csv', file_name: 'sample.csv',
        file_disk: 'local', tenant_id: null, header_row: 1, chunk_size: 100,
    ), userId: $userId);

    expect($session->fresh()->user_id)->toBe($userId);

    $template = ImportMappingTemplate::query()->create([
        'user_id' => $userId, 'importable_type' => FakeImportModel::class, 'template_name' => 'uuid',
        'template_data' => [],
    ]);
    expect($template->fresh()->user_id)->toBe($userId);
});
