<?php

use Illuminate\Support\Facades\Storage;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Regression for blank rows read as data rows.
 *
 * countRows(), readChunks() and readRange() used to treat every row after the
 * header as data, so a CSV with blank lines, or an XLSX formatted far below
 * its data (empty formula results, blank strings), produced one failed import
 * row per blank row. Blank rows are now skipped consistently in all three,
 * and row_number still reports the real line in the file.
 */
beforeEach(function () {
    Storage::fake('local');
});

/**
 * Build a minimal XLSX from raw <sheetData> rows.
 *
 * @param  list<string>  $sharedStrings
 */
function blankRowsXlsx(array $sharedStrings, string $sheetData): string
{
    $path = tempnam(sys_get_temp_dir(), 'blank_rows_test_');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'</Types>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="0" applyFill="1"/></cellXfs></styleSheet>');

    $si = implode('', array_map(
        static fn (string $s) => '<si><t xml:space="preserve">'.htmlspecialchars($s, ENT_XML1).'</t></si>',
        $sharedStrings,
    ));
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$si.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData>'.$sheetData.'</sheetData></worksheet>');
    $zip->close();

    $bytes = file_get_contents($path);
    @unlink($path);

    return $bytes;
}

/**
 * @return list<array{row_number: int, data: array<string, mixed>}>
 */
function readAllRows(string $path, array $headers, int $chunkSize = 10, ?array &$starts = null): array
{
    $rows = [];
    $starts = [];

    (new FileReaderService)->readChunks(
        filePath: $path,
        disk: 'local',
        headers: $headers,
        headerRow: 1,
        chunkSize: $chunkSize,
        callback: function (array $chunk, int $startRow) use (&$rows, &$starts) {
            $starts[] = $startRow;
            array_push($rows, ...$chunk);
        },
    );

    return $rows;
}

/**
 * @return list<array{row_number: int, data: array<string, mixed>}>
 */
function readRangeRows(string $path, array $headers, int $start, int $limit): array
{
    $rows = [];

    (new FileReaderService)->readRange(
        filePath: $path,
        disk: 'local',
        headers: $headers,
        headerRow: 1,
        startDataRow: $start,
        limit: $limit,
        chunkSize: 10,
        callback: function (array $chunk) use (&$rows) {
            array_push($rows, ...$chunk);
        },
    );

    return $rows;
}

it('skips styled empty XLSX rows between and after the data rows', function () {
    // Shared strings: 0 code, 1 name, 2 C1, 3 Alpha, 4 C2, 5 Beta, 6 blanks
    $strings = ['code', 'name', 'C1', 'Alpha', 'C2', 'Beta', " \u{00A0}\t "];

    $sheet = '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
        .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c></row>'
        // Styled cells with no value (the shape Excel writes for a formatted row)
        .'<row r="3"><c r="A3" s="1"/><c r="B3" s="1"/></row>'
        // A formula dragged down that evaluates to ""
        .'<row r="4"><c r="A4" t="str"><f>IF(B4="","",B4)</f><v></v></c><c r="B4" s="1"/></row>'
        // A whitespace-only string (spaces, NBSP, tab)
        .'<row r="5"><c r="A5" t="s"><v>6</v></c><c r="B5" s="1"><v></v></c></row>'
        // Row 6 is left out entirely, as Excel does for an untouched row
        .'<row r="7"><c r="A7" t="s"><v>4</v></c><c r="B7" t="s"><v>5</v></c></row>';

    // Formatting carried far below the data
    for ($r = 8; $r <= 40; $r++) {
        $sheet .= $r % 2 === 0
            ? "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"str\"><f>IF(B{$r}=\"\",\"\",B{$r})</f><v></v></c></row>"
            : "<row r=\"{$r}\"><c r=\"B{$r}\" t=\"s\"><v>6</v></c></row>";
    }

    Storage::disk('local')->put('imports/blank.xlsx', blankRowsXlsx($strings, $sheet));

    $reader = new FileReaderService;
    $headers = $reader->readHeaders('imports/blank.xlsx', 'local');

    expect($headers)->toBe(['code', 'name'])
        ->and($reader->countRows('imports/blank.xlsx', 'local'))->toBe(2);

    $rows = readAllRows('imports/blank.xlsx', $headers, chunkSize: 1, starts: $starts);

    expect($rows)->toBe([
        ['row_number' => 2, 'data' => ['code' => 'C1', 'name' => 'Alpha']],
        ['row_number' => 7, 'data' => ['code' => 'C2', 'name' => 'Beta']],
    ])->and($starts)->toBe([1, 2]);

    // The second chunk job's window lands on the second data row
    expect(readRangeRows('imports/blank.xlsx', $headers, start: 2, limit: 1))->toBe([
        ['row_number' => 7, 'data' => ['code' => 'C2', 'name' => 'Beta']],
    ]);
});

it('skips blank and separator-only CSV lines but keeps a row holding "0"', function () {
    Storage::disk('local')->put(
        'imports/blank.csv',
        "code,name\n"     // 1
        ."C1,Alpha\n"     // 2
        ."\n"             // 3 blank line
        .",\n"            // 4 separators only
        ."  ,\t\n"        // 5 whitespace only
        ."C2,Beta\n"      // 6
        ."0,\n"           // 7 a real value
        ."\r\n"           // 8 CRLF blank line
        ."\n\n",          // 9, 10
    );

    $reader = new FileReaderService;
    $headers = $reader->readHeaders('imports/blank.csv', 'local');

    expect($headers)->toBe(['code', 'name'])
        ->and($reader->countRows('imports/blank.csv', 'local'))->toBe(3);

    $rows = readAllRows('imports/blank.csv', $headers, chunkSize: 2, starts: $starts);

    expect($rows)->toBe([
        ['row_number' => 2, 'data' => ['code' => 'C1', 'name' => 'Alpha']],
        ['row_number' => 6, 'data' => ['code' => 'C2', 'name' => 'Beta']],
        ['row_number' => 7, 'data' => ['code' => '0', 'name' => '']],
    ])->and($starts)->toBe([1, 3]);

    expect(readRangeRows('imports/blank.csv', $headers, start: 2, limit: 2))->toBe([
        ['row_number' => 6, 'data' => ['code' => 'C2', 'name' => 'Beta']],
        ['row_number' => 7, 'data' => ['code' => '0', 'name' => '']],
    ]);
});

it('counts zero data rows for a sheet that is only a header and formatting', function () {
    $sheet = '<row r="1"><c r="A1" t="s"><v>0</v></c></row>';
    for ($r = 2; $r <= 20; $r++) {
        $sheet .= "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"str\"><f>\"\"</f><v></v></c></row>";
    }

    Storage::disk('local')->put('imports/empty.xlsx', blankRowsXlsx(['code'], $sheet));

    expect((new FileReaderService)->countRows('imports/empty.xlsx', 'local'))->toBe(0)
        ->and(readAllRows('imports/empty.xlsx', ['code']))->toBe([]);
});
