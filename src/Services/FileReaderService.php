<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Reads an uploaded file (CSV or XLSX) and returns headers + rows.
 *
 * All reading is done via generators so neither CSV nor XLSX files are
 * ever fully loaded into RAM regardless of row count.
 */
class FileReaderService
{
    // ── Public API ────────────────────────────────────────────────────────

    public function readHeaders(string $filePath, string $disk, int $headerRow = 1): array
    {
        return $this->withLocalPath($filePath, $disk, function (string $localPath) use ($headerRow): array {
            foreach ($this->rows($localPath) as $index => $row) {
                if ($index + 1 === $headerRow) {
                    return array_map(static fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
                }
            }

            return [];
        });
    }

    /**
     * Iterate rows as header-keyed arrays, delivered in chunks.
     *
     * @param  callable(array $chunk, int $startRow): void  $callback
     */
    public function readChunks(
        string $filePath,
        string $disk,
        array $headers,
        int $headerRow,
        int $chunkSize,
        callable $callback,
    ): void {
        $this->withLocalPath($filePath, $disk, function (string $localPath) use ($headers, $headerRow, $chunkSize, $callback): void {
            $dataRow = 0;
            $chunk = [];

            foreach ($this->rows($localPath) as $rowIndex => $raw) {
                if ($rowIndex < $headerRow) {
                    continue;
                }

                $dataRow++;
                $mapped = [];
                foreach ($headers as $colIndex => $header) {
                    $mapped[$header] = $raw[$colIndex] ?? null;
                }

                $chunk[] = ['row_number' => $rowIndex + 1, 'data' => $mapped];

                if (count($chunk) >= $chunkSize) {
                    $callback($chunk, $dataRow - count($chunk) + 1);
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                $callback($chunk, $dataRow - count($chunk) + 1);
            }
        });
    }

    /**
     * Iterate a contiguous slice of data rows as header-keyed arrays.
     *
     * Used by ProcessImportChunkJob so each queued job streams only its own
     * [$startDataRow, $startDataRow + $limit) window (1-based among data
     * rows, i.e. rows after the header). Iteration stops as soon as the
     * window ends — the rest of a multi-thousand-row file is never read.
     *
     * @param  callable(array $chunk): void  $callback
     */
    public function readRange(
        string $filePath,
        string $disk,
        array $headers,
        int $headerRow,
        int $startDataRow,
        int $limit,
        int $chunkSize,
        callable $callback,
    ): void {
        $this->withLocalPath($filePath, $disk, function (string $localPath) use ($headers, $headerRow, $startDataRow, $limit, $chunkSize, $callback): void {
            $endDataRow = $startDataRow + $limit - 1;
            $dataRow = 0;
            $chunk = [];

            foreach ($this->rows($localPath) as $rowIndex => $raw) {
                if ($rowIndex < $headerRow) {
                    continue;
                }

                $dataRow++;

                if ($dataRow < $startDataRow) {
                    continue;
                }

                if ($dataRow > $endDataRow) {
                    break; // past this job's window — stop reading the file
                }

                $mapped = [];
                foreach ($headers as $colIndex => $header) {
                    $mapped[$header] = $raw[$colIndex] ?? null;
                }

                $chunk[] = ['row_number' => $rowIndex + 1, 'data' => $mapped];

                if (count($chunk) >= $chunkSize) {
                    $callback($chunk);
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                $callback($chunk);
            }
        });
    }

    public function countRows(string $filePath, string $disk, int $headerRow = 1): int
    {
        return $this->withLocalPath($filePath, $disk, function (string $localPath) use ($headerRow): int {
            $count = 0;

            foreach ($this->rows($localPath) as $index => $_) {
                if ($index >= $headerRow) {
                    $count++;
                }
            }

            return $count;
        });
    }

    // ── Unified row generator ─────────────────────────────────────────────

    private function rows(string $localPath): \Generator
    {
        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->csvRows($localPath),
            'xlsx', 'xls' => $this->xlsxRows($localPath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$extension}"),
        };
    }

    // ── CSV ───────────────────────────────────────────────────────────────

    /**
     * Stream CSV rows one-by-one via fgetcsv.
     * Memory usage: O(1) — only one row in RAM at a time.
     *
     * The delimiter is sniffed from the first line rather than hard-coded:
     * Excel saved as CSV under a Turkish/European locale uses ';' (the locale
     * list separator), and tab/pipe exports also occur. A UTF-8 BOM (written
     * by Excel on Windows) is stripped from the first line so the first header
     * is not polluted with \xEF\xBB\xBF. Every cell is forced to valid UTF-8.
     */
    private function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV file: {$path}");
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return; // empty file
            }

            $firstLine = $this->stripBom($firstLine);
            $delimiter = $this->detectCsvDelimiter($firstLine);

            $index = 0;
            yield $index++ => $this->normalizeCsvRow(str_getcsv(rtrim($firstLine, "\r\n"), $delimiter));

            while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
                yield $index++ => $this->normalizeCsvRow($row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Sniff the field delimiter from the header line by counting candidate
     * separators outside quoted segments. Falls back to ',' when ambiguous.
     */
    private function detectCsvDelimiter(string $line): string
    {
        $line = rtrim($line, "\r\n");

        // Drop quoted segments so delimiters inside quotes don't skew the count
        $unquoted = preg_replace('/"(?:[^"]|"")*"/', '', $line) ?? $line;

        $counts = [
            ',' => substr_count($unquoted, ','),
            ';' => substr_count($unquoted, ';'),
            "\t" => substr_count($unquoted, "\t"),
            '|' => substr_count($unquoted, '|'),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * Strip a leading UTF-8 BOM (Excel on Windows prepends \xEF\xBB\xBF).
     */
    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    /**
     * Force every cell in a CSV row to valid UTF-8.
     *
     * Excel saved-as-CSV on a Turkish/European Windows locale writes bytes in
     * Windows-1252 or ISO-8859-9, which corrupt Eloquent's json_encode cast
     * for `detected_headers`. mb_convert_encoding tries the candidate list in
     * order and re-encodes from whichever interpretation produces valid UTF-8;
     * already-valid UTF-8 input is returned unchanged.
     *
     * @param  list<string|null>  $row
     * @return list<string|null>
     */
    private function normalizeCsvRow(array $row): array
    {
        return array_map(
            fn ($v) => is_string($v)
                ? mb_convert_encoding($v, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-9, ISO-8859-1')
                : $v,
            $row,
        );
    }

    // ── XLSX ──────────────────────────────────────────────────────────────

    private function xlsxRows(string $path): \Generator
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open XLSX file: {$path}");
        }

        $sharedStrings = $this->readSharedStrings($zip);
        [$xfNumFmtIds, $customNumFmts] = $this->readStyles($zip);
        $date1904 = $this->readDate1904($zip);

        $sheetPath = $this->resolveFirstSheetPath($zip);
        $wsStream = $zip->getStream($sheetPath);

        if ($wsStream === false) {
            $zip->close();
            throw new \RuntimeException("Cannot open worksheet at '{$sheetPath}' inside XLSX.");
        }

        [$reader, $tmpPath] = $this->xmlReaderFromStream($wsStream);

        try {
            yield from $this->iterateWorksheet(
                $reader,
                $sharedStrings,
                $xfNumFmtIds,
                $customNumFmts,
                $date1904,
            );
        } finally {
            // Deterministic cleanup — replaces the deferred
            // register_shutdown_function() that previously leaked one
            // tempfile per sidecar across the lifetime of a queue worker.
            $reader->close();
            $zip->close();
            if ($tmpPath !== null && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Stream rows from a pre-opened worksheet XMLReader. Pure iteration —
     * no resource ownership so the caller can dispose deterministically.
     */
    private function iterateWorksheet(
        \XMLReader $reader,
        array $sharedStrings,
        array $xfNumFmtIds,
        array $customNumFmts,
        bool $date1904,
    ): \Generator {
        $rowIndex = -1;
        $currentCells = [];
        $maxCol = -1;

        $inCell = false;
        $cellColIdx = 0;
        $cellType = '';
        $cellStyleIdx = null;
        $inV = false;
        $vBuffer = '';

        while ($reader->read()) {
            $nodeType = $reader->nodeType;
            $nodeName = $reader->localName;

            switch (true) {
                case $nodeType === \XMLReader::ELEMENT && $nodeName === 'row':
                    $rowIndex++;
                    $currentCells = [];
                    $maxCol = -1;
                    break;

                case $nodeType === \XMLReader::END_ELEMENT && $nodeName === 'row':
                    if ($maxCol >= 0) {
                        $values = [];
                        for ($i = 0; $i <= $maxCol; $i++) {
                            $values[] = $currentCells[$i] ?? null;
                        }
                        yield $rowIndex => $values;
                    }
                    break;

                case $nodeType === \XMLReader::ELEMENT && $nodeName === 'c':
                    $ref = $reader->getAttribute('r') ?? '';
                    $cellType = $reader->getAttribute('t') ?? '';
                    $sAttr = $reader->getAttribute('s');
                    $cellStyleIdx = $sAttr !== null ? (int) $sAttr : null;
                    $inCell = true;
                    $vBuffer = '';
                    $inV = false;

                    preg_match('/^([A-Z]+)/', $ref, $m);
                    $cellColIdx = $this->columnLetterToIndex($m[1] ?? 'A');
                    break;

                case $nodeType === \XMLReader::END_ELEMENT && $nodeName === 'c':
                    if ($inCell) {
                        $value = $this->resolveCellValue(
                            $cellType,
                            $vBuffer,
                            $cellStyleIdx,
                            $sharedStrings,
                            $xfNumFmtIds,
                            $customNumFmts,
                            $date1904,
                        );

                        $currentCells[$cellColIdx] = $value;
                        if ($cellColIdx > $maxCol) {
                            $maxCol = $cellColIdx;
                        }

                        $inCell = false;
                        $cellType = '';
                        $cellStyleIdx = null;
                        $vBuffer = '';
                    }
                    break;

                case $nodeType === \XMLReader::ELEMENT && $nodeName === 'v' && $inCell:
                    $inV = true;
                    $vBuffer = '';
                    break;

                case $nodeType === \XMLReader::TEXT && $inV:
                    $vBuffer .= $reader->value;
                    break;

                case $nodeType === \XMLReader::END_ELEMENT && $nodeName === 'v':
                    $inV = false;
                    break;
            }
        }
    }

    private function resolveCellValue(
        string $cellType,
        string $rawValue,
        ?int $styleIdx,
        array $sharedStrings,
        array $xfNumFmtIds,
        array $customNumFmts,
        bool $date1904,
    ): mixed {
        if ($rawValue === '') {
            return null;
        }

        return match ($cellType) {
            's' => $sharedStrings[(int) $rawValue] ?? null,
            'b' => (bool) (int) $rawValue,
            'e' => null,
            'str' => $rawValue,
            default => $this->resolveNumericCell($rawValue, $styleIdx, $xfNumFmtIds, $customNumFmts, $date1904),
        };
    }

    private function resolveNumericCell(
        string $rawValue,
        ?int $styleIdx,
        array $xfNumFmtIds,
        array $customNumFmts,
        bool $date1904,
    ): string {
        if ($styleIdx !== null && isset($xfNumFmtIds[$styleIdx])) {
            $numFmtId = $xfNumFmtIds[$styleIdx];

            if ($this->isDateNumFmt($numFmtId, $customNumFmts)) {
                return $this->excelSerialToDateString((float) $rawValue, $date1904);
            }
        }

        return $rawValue;
    }

    private function readStyles(\ZipArchive $zip): array
    {
        $stream = $zip->getStream('xl/styles.xml');

        if ($stream === false) {
            return [[], []];
        }

        [$reader, $tmpPath] = $this->xmlReaderFromStream($stream);
        $xfNumFmtIds = [];
        $customNumFmts = [];
        $inCellXfs = false;

        try {
            while ($reader->read()) {
                $type = $reader->nodeType;
                $name = $reader->localName;

                if ($type === \XMLReader::ELEMENT && $name === 'numFmt') {
                    $id = (int) ($reader->getAttribute('numFmtId') ?? -1);
                    $code = $reader->getAttribute('formatCode') ?? '';
                    if ($id >= 164) {
                        $customNumFmts[$id] = $code;
                    }
                    continue;
                }

                if ($type === \XMLReader::ELEMENT && $name === 'cellXfs') {
                    $inCellXfs = true;
                    continue;
                }
                if ($type === \XMLReader::END_ELEMENT && $name === 'cellXfs') {
                    $inCellXfs = false;
                    continue;
                }

                if ($inCellXfs && $type === \XMLReader::ELEMENT && $name === 'xf') {
                    $xfNumFmtIds[] = (int) ($reader->getAttribute('numFmtId') ?? 0);
                }
            }
        } finally {
            $reader->close();
            @unlink($tmpPath);
        }

        return [$xfNumFmtIds, $customNumFmts];
    }

    private function isDateNumFmt(int $numFmtId, array $customNumFmts): bool
    {
        if (($numFmtId >= 14 && $numFmtId <= 22) || ($numFmtId >= 45 && $numFmtId <= 47)) {
            return true;
        }

        if ($numFmtId >= 164 && isset($customNumFmts[$numFmtId])) {
            return $this->formatCodeIsDate($customNumFmts[$numFmtId]);
        }

        return false;
    }

    private function formatCodeIsDate(string $code): bool
    {
        $stripped = preg_replace('/"[^"]*"/', '', $code) ?? $code;
        $stripped = preg_replace('/\[[^]]*]/', '', $stripped);
        $stripped = strtolower($stripped);

        $hasDateToken = (bool) preg_match('/[ydhms]/', $stripped);
        $hasNumberToken = (bool) preg_match('/[0#?]/', $stripped);

        return $hasDateToken && ! $hasNumberToken;
    }

    private function excelSerialToDateString(float $serial, bool $date1904): string
    {
        if ($date1904) {
            $days = (int) $serial;
            $epoch = \Carbon\Carbon::create(1904, 1, 1, 0, 0, 0, 'UTC');
        } else {
            if ($serial <= 60) {
                $serial = max(1.0, $serial);
            } else {
                $serial -= 1;
            }
            $days = (int) $serial;
            $epoch = \Carbon\Carbon::create(1900, 1, 1, 0, 0, 0, 'UTC');
        }

        $date = $epoch->copy()->addDays($days - 1);
        $fraction = $serial - (int) $serial;

        if ($fraction > 0) {
            $date->addSeconds((int) round($fraction * 86400));
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function readDate1904(\ZipArchive $zip): bool
    {
        $stream = $zip->getStream('xl/workbook.xml');

        if ($stream === false) {
            return false;
        }

        [$reader, $tmpPath] = $this->xmlReaderFromStream($stream);
        $is1904 = false;

        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'workbookPr') {
                    $val = $reader->getAttribute('date1904') ?? '0';
                    $is1904 = $val === '1' || strtolower($val) === 'true';
                    break;
                }
            }
        } finally {
            $reader->close();
            @unlink($tmpPath);
        }

        return $is1904;
    }

    private function resolveFirstSheetPath(\ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';

        $rId = $this->readFirstSheetRId($zip);

        if ($rId === null) {
            return $fallback;
        }

        $target = $this->readRelTarget($zip, $rId);

        if ($target === null) {
            return $fallback;
        }

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        if (! str_starts_with($target, 'xl/')) {
            return 'xl/'.$target;
        }

        return $target;
    }

    private function readFirstSheetRId(\ZipArchive $zip): ?string
    {
        $stream = $zip->getStream('xl/workbook.xml');

        if ($stream === false) {
            return null;
        }

        [$reader, $tmpPath] = $this->xmlReaderFromStream($stream);
        $rId = null;

        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'sheet') {
                    $rId = $reader->getAttribute('r:id')
                        ?? $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    break;
                }
            }
        } finally {
            $reader->close();
            @unlink($tmpPath);
        }

        return $rId;
    }

    private function readRelTarget(\ZipArchive $zip, string $rId): ?string
    {
        $stream = $zip->getStream('xl/_rels/workbook.xml.rels');

        if ($stream === false) {
            return null;
        }

        [$reader, $tmpPath] = $this->xmlReaderFromStream($stream);
        $target = null;

        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                    if ($reader->getAttribute('Id') === $rId) {
                        $target = $reader->getAttribute('Target');
                        break;
                    }
                }
            }
        } finally {
            $reader->close();
            @unlink($tmpPath);
        }

        return $target;
    }

    /**
     * Spool a ZipArchive sub-stream into a tempfile and return both the
     * XMLReader and the tempfile path so the caller can dispose deterministically.
     *
     * The XMLReader extension cannot read from arbitrary PHP streams (and
     * the streams returned by ZipArchive::getStream() are not seekable),
     * so a tempfile is unavoidable today. The worksheet stream is copied
     * chunk-by-chunk via stream_copy_to_stream to avoid loading the entire
     * XML into RAM (previously: stream_get_contents() — full-file slurp).
     *
     * TODO: investigate switching to a true streaming XMLReader::open()
     *       loop yielding rows for the worksheet payload — left as a
     *       follow-up so the deterministic cleanup fix lands first.
     *
     * @return array{0: \XMLReader, 1: string} Reader and absolute tempfile path
     */
    private function xmlReaderFromStream($stream): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_');
        $tmpHandle = fopen($tmpPath, 'wb');

        if ($tmpHandle === false) {
            fclose($stream);
            @unlink($tmpPath);
            throw new \RuntimeException("Cannot open tempfile {$tmpPath} for XLSX spooling.");
        }

        try {
            stream_copy_to_stream($stream, $tmpHandle);
        } finally {
            fclose($tmpHandle);
            fclose($stream);
        }

        $reader = new \XMLReader;
        if (! $reader->open($tmpPath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("XMLReader could not open spooled XLSX part at {$tmpPath}.");
        }

        return [$reader, $tmpPath];
    }

    private function readSharedStrings(\ZipArchive $zip): array
    {
        $stream = $zip->getStream('xl/sharedStrings.xml');

        if ($stream === false) {
            return [];
        }

        [$reader, $tmpPath] = $this->xmlReaderFromStream($stream);
        $sharedStrings = [];
        $texts = [];
        $inT = false;

        try {
            while ($reader->read()) {
                $type = $reader->nodeType;
                $name = $reader->localName;

                if ($type === \XMLReader::ELEMENT && $name === 't') {
                    $inT = true;
                } elseif ($type === \XMLReader::TEXT && $inT) {
                    $texts[] = $reader->value;
                    $inT = false;
                } elseif ($type === \XMLReader::END_ELEMENT && $name === 'si') {
                    $sharedStrings[] = implode('', $texts);
                    $texts = [];
                }
            }
        } finally {
            $reader->close();
            @unlink($tmpPath);
        }

        return $sharedStrings;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function columnLetterToIndex(string $letters): int
    {
        $result = 0;
        foreach (str_split(strtoupper($letters)) as $char) {
            $result = $result * 26 + (ord($char) - ord('A') + 1);
        }

        return $result - 1;
    }

    /**
     * Resolve a usable local filesystem path for $filePath on $disk and pass
     * it to the given $work closure. If the disk's driver implements path()
     * the file is read in place; otherwise (S3, GCS, Azure, in-memory…)
     * its readStream() is spooled to a tempfile that is unlinked in finally.
     *
     * @template T
     *
     * @param  callable(string $localPath): T  $work
     * @return T
     */
    private function withLocalPath(string $filePath, string $disk, callable $work): mixed
    {
        $filesystem = Storage::disk($disk);
        $tmpPath = null;

        try {
            $localPath = $filesystem->path($filePath);
        } catch (\Throwable $e) {
            // path() is not supported on this driver — fall through to
            // the readStream() spooling path below.
            $localPath = null;
        }

        if ($localPath === null) {
            $stream = $filesystem->readStream($filePath);

            if ($stream === false || $stream === null) {
                throw new \RuntimeException(
                    "Cannot open stream for '{$filePath}' on disk '{$disk}'.",
                );
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'tmp';
            $tmpPath = tempnam(sys_get_temp_dir(), 'ie_').'.'.$extension;
            $tmpHandle = fopen($tmpPath, 'wb');

            if ($tmpHandle === false) {
                fclose($stream);
                @unlink($tmpPath);
                throw new \RuntimeException("Cannot open spool tempfile {$tmpPath}.");
            }

            try {
                stream_copy_to_stream($stream, $tmpHandle);
            } finally {
                fclose($tmpHandle);
                fclose($stream);
            }

            $localPath = $tmpPath;
        }

        try {
            return $work($localPath);
        } finally {
            if ($tmpPath !== null && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
