<?php

use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Regression for I4: FileReaderService::localPath() called
 * Storage::disk($disk)->path() unconditionally, which throws
 * `This driver does not support retrieving absolute paths` on
 * remote/cloud disks (S3, GCS, Azure, in-memory test disks).
 *
 * After the fix, the reader spools the remote file to a tempfile via
 * readStream() and cleans it up deterministically.
 */
beforeEach(function () {
    // Fake a non-local disk by wrapping the local disk in an adapter that
    // throws on path() — same shape that S3/GCS expose. We register it as
    // a custom driver and swap it in for the "remote" disk key.
    Storage::fake('local');
    Storage::disk('local')->put(
        'imports/sample.csv',
        file_get_contents(__DIR__.'/../Fixtures/sample.csv'),
    );

    $localDisk = Storage::disk('local');

    $remote = new class($localDisk) extends \Illuminate\Filesystem\FilesystemAdapter
    {
        public function __construct(private \Illuminate\Contracts\Filesystem\Filesystem $local)
        {
            // Skip parent constructor — we forward every method to $local.
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
    };

    Storage::set('remote', $remote);
});

it('reads headers from a disk whose driver does not support path()', function () {
    $reader = new FileReaderService;

    $headers = $reader->readHeaders('imports/sample.csv', 'remote');

    expect($headers)->not->toBeEmpty();
});

it('reads chunks from a remote-style disk via readStream() spooling', function () {
    $reader = new FileReaderService;
    $rows = [];

    $reader->readChunks(
        filePath: 'imports/sample.csv',
        disk: 'remote',
        headers: ['sku', 'name', 'price'],
        headerRow: 1,
        chunkSize: 10,
        callback: function (array $chunk) use (&$rows) {
            foreach ($chunk as $r) {
                $rows[] = $r['data'];
            }
        },
    );

    expect($rows)->not->toBeEmpty();
});
