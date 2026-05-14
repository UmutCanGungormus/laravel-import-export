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
        $localPath = $this->localPath($filePath, $disk);

        foreach ($this->rows($localPath) as $index => $row) {
            if ($index + 1 === $headerRow) {
                return array_map(static fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
            }
        }

        return [];
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
        $localPath = $this->localPath($filePath, $disk);
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
    }

    public function countRows(string $filePath, string $disk, int $headerRow = 1): int
    {
        $localPath = $this->localPath($filePath, $disk);
        $count = 0;

        foreach ($this->rows($localPath) as $index => $_) {
            if ($index >= $headerRow) {
                $count++;
            }
        }

        return $count;
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

    private function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV file: {$path}");
        }

        try {
            $index = 0;
            while (($row = fgetcsv($handle)) !== false) {
                yield $index++ => $row;
            }
        } finally {
            fclose($handle);
        }
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

        $reader = $this->xmlReaderFromStream($wsStream);

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

        $reader->close();
        $zip->close();
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

        $reader = $this->xmlReaderFromStream($stream);
        $xfNumFmtIds = [];
        $customNumFmts = [];
        $inCellXfs = false;

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

        $reader->close();

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

        $reader = $this->xmlReaderFromStream($stream);
        $is1904 = false;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'workbookPr') {
                $val = $reader->getAttribute('date1904') ?? '0';
                $is1904 = $val === '1' || strtolower($val) === 'true';
                break;
            }
        }

        $reader->close();

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

        $reader = $this->xmlReaderFromStream($stream);
        $rId = null;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $rId = $reader->getAttribute('r:id')
                    ?? $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                break;
            }
        }

        $reader->close();

        return $rId;
    }

    private function readRelTarget(\ZipArchive $zip, string $rId): ?string
    {
        $stream = $zip->getStream('xl/_rels/workbook.xml.rels');

        if ($stream === false) {
            return null;
        }

        $reader = $this->xmlReaderFromStream($stream);
        $target = null;

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                if ($reader->getAttribute('Id') === $rId) {
                    $target = $reader->getAttribute('Target');
                    break;
                }
            }
        }

        $reader->close();

        return $target;
    }

    private function xmlReaderFromStream($stream): \XMLReader
    {
        $reader = new \XMLReader;

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($tmp, stream_get_contents($stream));
        fclose($stream);

        $reader->open($tmp);
        register_shutdown_function(static fn () => @unlink($tmp));

        return $reader;
    }

    private function readSharedStrings(\ZipArchive $zip): array
    {
        $stream = $zip->getStream('xl/sharedStrings.xml');

        if ($stream === false) {
            return [];
        }

        $reader = $this->xmlReaderFromStream($stream);
        $sharedStrings = [];
        $texts = [];
        $inT = false;

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

        $reader->close();

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

    private function localPath(string $filePath, string $disk): string
    {
        return Storage::disk($disk)->path($filePath);
    }
}
