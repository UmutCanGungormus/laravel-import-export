<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Actions\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Actions\StartImportAction;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Jobs\ProcessImportJob;
use Umutcangungormus\LaravelImportExport\Models\ImportFailure;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
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

function initSession(): ImportSession
{
    $data = new InitializeImportData(
        model_class: FakeImportModel::class,
        file_path: 'imports/sample.csv',
        file_name: 'sample.csv',
        file_disk: 'local',
        tenant_id: null,
        header_row: 1,
        chunk_size: 100,
    );

    return app(InitializeImportAction::class)->execute($data, userId: null);
}

it('runs Initialize → Start → Job and lands the session in Completed', function () {
    Bus::fake([ProcessImportJob::class]); // capture dispatches but let us run by hand

    $session = initSession();

    app(StartImportAction::class)->execute($session, dispatch: false);

    // Manually run the job (queue.default = sync; we bypass DB::afterCommit dispatch by passing dispatch=false).
    app(ProcessImportJob::class, ['sessionId' => $session->id])->handle(
        app(\Umutcangungormus\LaravelImportExport\Services\FileReaderService::class),
        app(\Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract::class),
    );

    $session->refresh();

    expect($session->status)->toBe(ImportStatus::Completed);
    expect($session->successful_rows)->toBe(3);
    expect($session->failed_rows)->toBe(0);

    // Processor saw three rows
    expect(FakeImportProcessor::$preparedRows)->toHaveCount(3);
    expect(FakeImportProcessor::$afterRows)->toHaveCount(3);

    // Rows landed in fake_import_items
    expect(FakeImportModel::count())->toBe(3);
});

it('records a failure row and lands the session in CompletedWithErrors when one row throws', function () {
    Bus::fake([ProcessImportJob::class]);

    $session = initSession();
    app(StartImportAction::class)->execute($session, dispatch: false);

    FakeImportProcessor::$throwOnRow2 = true;

    app(ProcessImportJob::class, ['sessionId' => $session->id])->handle(
        app(\Umutcangungormus\LaravelImportExport\Services\FileReaderService::class),
        app(\Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract::class),
    );

    $session->refresh();

    expect($session->status)->toBe(ImportStatus::CompletedWithErrors);
    expect(ImportFailure::where('import_session_id', $session->id)->count())->toBe(1);
    expect($session->failed_rows)->toBe(1);
    expect($session->successful_rows)->toBe(2);
});

it('dispatches ProcessImportJob through DB::afterCommit when StartImportAction is called normally', function () {
    Queue::fake();

    $session = initSession();

    app(StartImportAction::class)->execute($session);

    Queue::assertPushed(ProcessImportJob::class, fn ($job) => $job->sessionId === $session->id);
});
