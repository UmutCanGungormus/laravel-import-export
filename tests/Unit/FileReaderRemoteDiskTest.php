<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Regression for reading import files off non-local disks.
 *
 * FileReaderService::withLocalPath() used to decide "is this disk local?" by
 * calling Storage::disk($disk)->path() inside a try/catch, assuming remote
 * drivers throw. They do not: FilesystemAdapter::path() only prefixes the key
 * via PathPrefixer, and AwsS3V3Adapter does not override it — so an S3/OBS
 * disk returns a plain string like "staging/imports/x.xlsx". The spooling
 * branch was therefore never reached on real S3 and ZipArchive::open() was
 * handed a bucket key ("Cannot open XLSX file: staging/imports/x.xlsx").
 *
 * Two disk shapes are covered here:
 *   - `s3_like`        path() returns a non-local prefixed string (S3, GCS, SFTP)
 *   - `custom_no_path` path() throws (custom Storage::extend() drivers)
 */
beforeEach(function () {
    Storage::fake('local');

    foreach (['sample.csv', 'sample.xlsx'] as $fixture) {
        Storage::disk('local')->put(
            "imports/{$fixture}",
            file_get_contents(__DIR__."/../Fixtures/{$fixture}"),
        );
    }

    $localRoot = Storage::disk('local')->path('');

    // Same shape as AwsS3V3Adapter: a real FilesystemAdapter whose reads work,
    // but whose configured root is a bucket-style prefix rather than a
    // filesystem path — so path() returns a string that is not a local file.
    $flysystemAdapter = new LocalFilesystemAdapter($localRoot);

    Storage::set('s3_like', new FilesystemAdapter(
        new Flysystem($flysystemAdapter),
        $flysystemAdapter,
        ['root' => 'bogus-bucket-prefix/'],
    ));

    // A driver that genuinely does not support absolute paths — the case the
    // original try/catch was written for. Still supported.
    $localDisk = Storage::disk('local');

    Storage::set('custom_no_path', new class($localDisk) extends FilesystemAdapter
    {
        public function __construct(private \Illuminate\Contracts\Filesystem\Filesystem $local)
        {
            // Skip parent constructor — every method is forwarded to $local.
        }

        public function path($path): string
        {
            throw new \RuntimeException('This driver does not support retrieving absolute paths.');
        }

        public function readStream($path)
        {
            return $this->local->readStream($path);
        }

        public function exists($path): bool
        {
            return $this->local->exists($path);
        }

        public function get($path): ?string
        {
            return $this->local->get($path);
        }
    });
});

/**
 * Snapshot the `ie_*` spool tempfiles currently in sys_get_temp_dir().
 *
 * Assumes a sequential run (the suite's CI entrypoint is plain `pest`): under
 * `--parallel`, a sibling process spooling at the same moment would show up in
 * the diff.
 *
 * @return array<int, string>
 */
function ieSpoolTempFiles(): array
{
    return array_values(array_filter(
        scandir(sys_get_temp_dir()) ?: [],
        static fn (string $name) => str_starts_with($name, 'ie_'),
    ));
}

// ── Stub fidelity ─────────────────────────────────────────────────────────
// If these drift, every test below stops proving anything.

it('models a remote disk whose path() returns a non-local string without throwing', function () {
    $resolved = Storage::disk('s3_like')->path('imports/sample.xlsx');

    // Bucket-style key, not a filesystem path — and crucially, no exception.
    // (Not asserted verbatim: PathPrefixer joins with DIRECTORY_SEPARATOR.)
    expect($resolved)->toContain('bogus-bucket-prefix')
        ->and($resolved)->toContain('sample.xlsx')
        ->and(is_file($resolved))->toBeFalse()
        ->and(Storage::disk('s3_like')->exists('imports/sample.xlsx'))->toBeTrue();
});

it('matches how Laravel S3 disks expose path() — inherited, never overridden', function () {
    if (! class_exists(\Illuminate\Filesystem\AwsS3V3Adapter::class)) {
        $this->markTestSkipped('S3 adapter not installed.');
    }

    $declaring = (new ReflectionClass(\Illuminate\Filesystem\AwsS3V3Adapter::class))
        ->getMethod('path')
        ->getDeclaringClass()
        ->getName();

    // If a future Laravel gives S3 its own throwing path(), this fails and the
    // detection in withLocalPath() should be revisited.
    expect($declaring)->toBe(FilesystemAdapter::class);
});

// ── XLSX over a remote disk (the reported production failure) ─────────────

it('reads XLSX headers from a disk whose path() is not a local file', function () {
    $headers = (new FileReaderService)->readHeaders('imports/sample.xlsx', 's3_like', headerRow: 1);

    expect($headers)->toBe(['sku', 'name']);
});

it('counts XLSX rows from a disk whose path() is not a local file', function () {
    $count = (new FileReaderService)->countRows('imports/sample.xlsx', 's3_like', headerRow: 1);

    expect($count)->toBe(1);
});

it('reads XLSX chunks from a disk whose path() is not a local file', function () {
    $captured = [];

    (new FileReaderService)->readChunks(
        filePath: 'imports/sample.xlsx',
        disk: 's3_like',
        headers: ['sku', 'name'],
        headerRow: 1,
        chunkSize: 10,
        callback: function (array $chunk) use (&$captured) {
            foreach ($chunk as $row) {
                $captured[] = $row['data'];
            }
        },
    );

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['sku'])->toBe('SKU-1');
});

it('reads a row window from a disk whose path() is not a local file', function () {
    $captured = [];

    // readRange() is the path every ProcessImportChunkJob takes.
    (new FileReaderService)->readRange(
        filePath: 'imports/sample.csv',
        disk: 's3_like',
        headers: ['sku', 'name', 'price'],
        headerRow: 1,
        startDataRow: 2,
        limit: 2,
        chunkSize: 10,
        callback: function (array $chunk) use (&$captured) {
            foreach ($chunk as $row) {
                $captured[] = $row['data'];
            }
        },
    );

    expect(array_column($captured, 'sku'))->toBe(['A-002', 'A-003']);
});

// ── Custom driver without path() ───────────────────────────────────────────

it('reads headers from a driver whose path() throws', function () {
    $headers = (new FileReaderService)->readHeaders('imports/sample.csv', 'custom_no_path');

    expect($headers)->toBe(['sku', 'name', 'price']);
});

it('reads chunks from a driver whose path() throws', function () {
    $rows = [];

    (new FileReaderService)->readChunks(
        filePath: 'imports/sample.csv',
        disk: 'custom_no_path',
        headers: ['sku', 'name', 'price'],
        headerRow: 1,
        chunkSize: 10,
        callback: function (array $chunk) use (&$rows) {
            foreach ($chunk as $r) {
                $rows[] = $r['data'];
            }
        },
    );

    expect($rows)->toHaveCount(3);
});

// ── Local disks and error surfaces ────────────────────────────────────────

it('still reads a local disk in place without spooling', function () {
    $before = ieSpoolTempFiles();

    $headers = (new FileReaderService)->readHeaders('imports/sample.xlsx', 'local', headerRow: 1);

    expect($headers)->toBe(['sku', 'name'])
        ->and(ieSpoolTempFiles())->toBe($before);
});

it('cleans up every spool tempfile after a remote read', function () {
    $before = ieSpoolTempFiles();

    $reader = new FileReaderService;
    $reader->readHeaders('imports/sample.xlsx', 's3_like', headerRow: 1);
    $reader->countRows('imports/sample.xlsx', 's3_like', headerRow: 1);
    $reader->readChunks('imports/sample.csv', 's3_like', ['sku', 'name', 'price'], 1, 10, fn () => null);

    // Both the tempnam() reservation and the extension-suffixed spool file
    // must be unlinked — the reservation used to leak one empty file per read.
    expect(ieSpoolTempFiles())->toBe($before);
});

it('reports a missing remote object instead of failing inside the parser', function () {
    expect(fn () => (new FileReaderService)->readHeaders('imports/missing.xlsx', 's3_like'))
        ->toThrow(RuntimeException::class, "Cannot open stream for 'imports/missing.xlsx' on disk 's3_like'.");
});

it('surfaces an unconfigured disk as a driver error, not a stream error', function () {
    expect(fn () => (new FileReaderService)->readHeaders('imports/sample.xlsx', 'no_such_disk'))
        ->toThrow(InvalidArgumentException::class);
});
