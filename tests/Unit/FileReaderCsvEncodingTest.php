<?php

use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Regression for CSV cell encoding.
 *
 * normalizeCsvRow() used to run every cell through
 * mb_convert_encoding($v, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-9, ISO-8859-1').
 * mbstring scores a multi-encoding source list instead of taking the first
 * valid candidate, so:
 *   - a valid UTF-8 cell starting with "Ş" (C5 9E, also valid Windows-1252)
 *     was re-encoded: "Şirket Yöneticisi" became "Åžirket YÃ¶neticisi";
 *   - a CSV saved by Excel on a Turkish Windows locale (Windows-1254) was
 *     decoded as Windows-1252: ş/ğ/ı came out as þ/ð/ý.
 */
beforeEach(function () {
    Storage::fake('local');
});

/**
 * @return list<array<string, mixed>>
 */
function readCsvData(string $path, array $headers): array
{
    $captured = [];

    (new FileReaderService)->readChunks(
        filePath: $path,
        disk: 'local',
        headers: $headers,
        headerRow: 1,
        chunkSize: 10,
        callback: function (array $chunk) use (&$captured) {
            foreach ($chunk as $row) {
                $captured[] = $row['data'];
            }
        },
    );

    return $captured;
}

it('keeps valid UTF-8 cells that start with Ş unchanged', function () {
    Storage::disk('local')->put(
        'imports/utf8.csv',
        "Şube;Rol\nŞUBE;Şirket Yöneticisi\nSatış;Gülşen Öztürk\n",
    );

    expect((new FileReaderService)->readHeaders('imports/utf8.csv', 'local'))
        ->toBe(['Şube', 'Rol']);

    expect(readCsvData('imports/utf8.csv', ['Şube', 'Rol']))->toBe([
        ['Şube' => 'ŞUBE', 'Rol' => 'Şirket Yöneticisi'],
        ['Şube' => 'Satış', 'Rol' => 'Gülşen Öztürk'],
    ]);
});

it('keeps valid UTF-8 cells unchanged when the file has a BOM', function () {
    Storage::disk('local')->put(
        'imports/utf8-bom.csv',
        "\xEF\xBB\xBFRol\nŞirket Yöneticisi\n",
    );

    expect(readCsvData('imports/utf8-bom.csv', ['Rol']))->toBe([
        ['Rol' => 'Şirket Yöneticisi'],
    ]);
});

it('decodes Turkish Windows-1254 CSV bytes as Turkish', function () {
    $utf8 = "Şube;Rol\nİĞÜŞÖÇ;Şirket Yöneticisi ığüşöç\n";
    $cp1254 = mb_convert_encoding($utf8, 'Windows-1254', 'UTF-8');

    expect(mb_check_encoding($cp1254, 'UTF-8'))->toBeFalse();

    Storage::disk('local')->put('imports/cp1254.csv', $cp1254);

    expect((new FileReaderService)->readHeaders('imports/cp1254.csv', 'local'))
        ->toBe(['Şube', 'Rol']);

    expect(readCsvData('imports/cp1254.csv', ['Şube', 'Rol']))->toBe([
        ['Şube' => 'İĞÜŞÖÇ', 'Rol' => 'Şirket Yöneticisi ığüşöç'],
    ]);
});
