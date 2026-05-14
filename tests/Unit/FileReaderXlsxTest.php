<?php

use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Regression for I5: xmlReaderFromStream() used to register every tempfile
 * with register_shutdown_function(), so a long-running queue worker would
 * leak O(5 * N) tempfiles per XLSX import until the process exited. The
 * fix is deterministic cleanup tied to the reader's lifetime.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put(
        'imports/sample.xlsx',
        file_get_contents(__DIR__.'/../Fixtures/sample.xlsx'),
    );
});

/**
 * Snapshot the set of `xlsx_*` tempfiles currently present in
 * sys_get_temp_dir() so a test can diff against it.
 *
 * @return array<int, string>
 */
function xlsxTempFiles(): array
{
    return array_values(array_filter(
        scandir(sys_get_temp_dir()) ?: [],
        static fn (string $name) => str_starts_with($name, 'xlsx_'),
    ));
}

it('reads XLSX headers without throwing', function () {
    $reader = new FileReaderService;

    $headers = $reader->readHeaders('imports/sample.xlsx', 'local', headerRow: 1);

    expect($headers)->toBe(['sku', 'name']);
});

it('reads XLSX data rows after the header row', function () {
    $reader = new FileReaderService;
    $captured = [];

    $reader->readChunks(
        filePath: 'imports/sample.xlsx',
        disk: 'local',
        headers: ['sku', 'name'],
        headerRow: 1,
        chunkSize: 10,
        callback: function (array $chunk) use (&$captured) {
            foreach ($chunk as $row) {
                $captured[] = $row['data'];
            }
        },
    );

    expect($captured)->toHaveCount(1);
    expect($captured[0]['sku'])->toBe('SKU-1');
});

it('does not leak xlsx_* tempfiles after iteration completes', function () {
    $before = xlsxTempFiles();

    $reader = new FileReaderService;
    $reader->readHeaders('imports/sample.xlsx', 'local', headerRow: 1);

    // Iterate a full read cycle so every sidecar (sharedStrings, styles,
    // workbook, rels, worksheet) goes through xmlReaderFromStream.
    $reader->readChunks(
        'imports/sample.xlsx',
        'local',
        ['sku', 'name'],
        1,
        10,
        fn () => null,
    );
    $reader->countRows('imports/sample.xlsx', 'local', 1);

    $after = xlsxTempFiles();

    // Net tempfile count must not have grown. The fix removes the deferred
    // register_shutdown_function() in favour of explicit cleanup in finally.
    expect(count($after))->toBeLessThanOrEqual(count($before));
});
