<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Actions\StartImportAction;
use Umutcangungormus\LaravelImportExport\Actions\UpdateMappingAction;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Data\UpdateMappingData;
use Umutcangungormus\LaravelImportExport\Enums\MultiColumnStrategy;
use Umutcangungormus\LaravelImportExport\Jobs\ProcessImportJob;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportModel;
use Umutcangungormus\LaravelImportExport\Tests\Fixtures\FakeImportProcessor;

/**
 * Several source columns feeding one target.
 *
 * The file carries two free-text columns (`Not A`, `Not B`) that the user wants
 * on the single `notes` target. That only happens when the field config marks
 * the target `multi` and the mapping carries a combine strategy — without both,
 * the historic "highest confidence column wins" behaviour has to survive
 * untouched, which is what the last cases here pin down.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/multi-column.csv', file_get_contents(__DIR__.'/../Fixtures/multi-column.csv'));

    $this->app['db']->connection()->getSchemaBuilder()->create('fake_import_items', function ($t) {
        $t->id();
        $t->string('sku');
        $t->string('name');
        $t->decimal('price', 8, 2)->nullable();
        $t->timestamps();
    });

    if (! Schema::hasTable('job_batches')) {
        Schema::create('job_batches', function ($t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->integer('total_jobs');
            $t->integer('pending_jobs');
            $t->integer('failed_jobs');
            $t->longText('failed_job_ids');
            $t->mediumText('options')->nullable();
            $t->integer('cancelled_at')->nullable();
            $t->integer('created_at');
            $t->integer('finished_at')->nullable();
        });
    }

    config()->set('import-export.models.'.FakeImportModel::class, [
        'processor' => FakeImportProcessor::class,
        'unique_by' => ['sku'],
        'fields' => [
            'sku' => ['required' => true, 'type' => 'string', 'validation' => ['required', 'string', 'max:64']],
            'name' => ['required' => true, 'type' => 'string', 'validation' => ['required', 'string', 'max:255']],
            // Free text, so several columns may feed it.
            'notes' => ['required' => false, 'type' => 'string', 'multi' => true],
            // Deliberately not multi: a strategy on it must be ignored.
            'price' => ['required' => false, 'type' => 'decimal'],
        ],
    ]);

    FakeImportProcessor::reset();
});

/** Uploads the two-note fixture and returns its session. */
function multiSession(): ImportSession
{
    return app(InitializeImportAction::class)->execute(new InitializeImportData(
        model_class: FakeImportModel::class,
        file_path: 'imports/multi-column.csv',
        file_name: 'multi-column.csv',
        file_disk: 'local',
        tenant_id: null,
        header_row: 1,
        chunk_size: 100,
    ), userId: null);
}

/**
 * Points the given columns at one target, optionally with a combine strategy.
 *
 * @param  array<int, string>  $columns  Source columns, in the order the user picked them
 */
function mapColumnsOnto(ImportSession $session, array $columns, string $target, ?string $strategy = null): void
{
    $action = app(UpdateMappingAction::class);

    foreach ($columns as $column) {
        $action->execute($session, new UpdateMappingData(
            source_column: $column,
            target_field: $target,
            confirmed: true,
            multi_strategy: $strategy,
        ));
    }
}

/** Runs the import and hands back the rows the processor saw. */
function runMultiImport(ImportSession $session): array
{
    app(StartImportAction::class)->execute($session, dispatch: false);
    app(ProcessImportJob::class, ['sessionId' => $session->id])->handle();

    return FakeImportProcessor::$preparedRows;
}

it('joins the cells with a space when the strategy is merge', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not A', 'Not B'], 'notes', 'merge');

    $rows = runMultiImport($session);

    expect($rows[0]['notes'])->toBe('ilk not ikinci not');

    // A blank cell drops out rather than leaving a stray separator.
    expect($rows[1]['notes'])->toBe('yalnız ikinci');
    expect($rows[2]['notes'])->toBe('yalnız ilk');
});

it('stores a column-keyed object when the strategy is json', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not A', 'Not B'], 'notes', 'json');

    $rows = runMultiImport($session);

    expect($rows[0]['notes'])->toBe('{"Not A":"ilk not","Not B":"ikinci not"}');
    expect(json_decode($rows[0]['notes'], true))->toBe(['Not A' => 'ilk not', 'Not B' => 'ikinci not']);

    // Only the columns the row actually filled are named.
    expect(json_decode($rows[1]['notes'], true))->toBe(['Not B' => 'yalnız ikinci']);
});

it('combines in file column order, not in the order the columns were picked', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not B', 'Not A'], 'notes', 'merge');

    $rows = runMultiImport($session);

    expect($rows[0]['notes'])->toBe('ilk not ikinci not');
});

it('leaves the target null when every column of the row is blank', function () {
    Storage::disk('local')->put('imports/multi-column.csv', "sku,name,Not A,Not B\nA-001,Widget,,\n");

    $session = multiSession();
    mapColumnsOnto($session, ['Not A', 'Not B'], 'notes', 'merge');

    $rows = runMultiImport($session);

    expect($rows[0]['notes'])->toBeNull();
});

it('keeps one column per target when no strategy was picked', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not A', 'Not B'], 'notes');

    $rows = runMultiImport($session);

    // Both columns claim `notes` at the same confidence, so the historic
    // tie-break stands: one of them wins outright and nothing is combined.
    expect($rows[0]['notes'])->toBeIn(['ilk not', 'ikinci not']);
});

it('ignores a strategy on a target the field config does not mark multi', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not A', 'Not B'], 'price', 'merge');

    $rows = runMultiImport($session);

    expect($session->multiColumnStrategies())->toBe([]);
    expect($rows[0]['price'])->toBeIn(['ilk not', 'ikinci not']);
});

it('reports the strategy per target', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not A', 'Not B'], 'notes', 'json');

    expect($session->multiColumnStrategies())->toBe(['notes' => MultiColumnStrategy::Json]);
});

it('persists the strategy on the mapping and drops it when the column is released', function () {
    $session = multiSession();
    mapColumnsOnto($session, ['Not A'], 'notes', 'merge');

    $mapping = $session->columnMappings()->where('source_column', 'Not A')->firstOrFail();
    expect($mapping->transformation_rules)->toBe(['multi_strategy' => 'merge']);

    app(UpdateMappingAction::class)->execute($session, new UpdateMappingData(
        source_column: 'Not A',
        target_field: null,
        confirmed: false,
    ));

    // A released column must not carry a strategy into whatever it is pointed
    // at next.
    expect($mapping->fresh()->transformation_rules)->toBeNull();
});
