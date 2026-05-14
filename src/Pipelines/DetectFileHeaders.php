<?php

namespace Umutcangungormus\LaravelImportExport\Pipelines;

use Closure;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Reads file headers and attaches them to the payload.
 *
 * Payload in : array with 'session', 'data' (InitializeImportData)
 * Payload out: same array + 'headers' key
 */
class DetectFileHeaders
{
    public function __construct(
        private FileReaderService $fileReader,
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        /** @var \Umutcangungormus\LaravelImportExport\Data\InitializeImportData $data */
        $data = $payload['data'];

        $headers = $this->fileReader->readHeaders($data->file_path, $data->file_disk, $data->header_row);
        $totalRows = $this->fileReader->countRows($data->file_path, $data->file_disk, $data->header_row);

        $payload['session']->update([
            'detected_headers' => $headers,
            'total_rows' => $totalRows,
        ]);

        $payload['headers'] = $headers;
        $payload['total_rows'] = $totalRows;

        return $next($payload);
    }
}
