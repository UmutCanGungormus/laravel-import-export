<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract;
use Umutcangungormus\LaravelImportExport\Models\ImportFailure;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

class FailureHandlerService implements FailureHandlerContract
{
    public function record(
        ImportSession $session,
        int $rowNumber,
        array $rowData,
        array $errors,
        ?string $exceptionMessage = null,
    ): void {
        ImportFailure::create([
            'import_session_id' => $session->id,
            'row_number' => $rowNumber,
            'row_data' => $rowData,
            'errors' => $errors,
            'exception_message' => $exceptionMessage,
        ]);

        $session->increment('failed_rows');
    }

    public function export(ImportSession $session): StreamedResponse
    {
        $failures = $session->failures()->orderBy('row_number')->get();

        $filename = 'import_failures_session_'.$session->id.'.csv';

        return response()->streamDownload(function () use ($failures) {
            $handle = fopen('php://output', 'w');

            // Headers
            fputcsv($handle, ['Row #', 'Errors', 'Raw Data']);

            foreach ($failures as $failure) {
                fputcsv($handle, [
                    $failure->row_number,
                    implode(' | ', $failure->errors ?? []).($failure->exception_message ? ' | Exception: '.$failure->exception_message : ''),
                    json_encode($failure->row_data, JSON_UNESCAPED_UNICODE),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function summary(ImportSession $session): array
    {
        $failures = $session->failures()->get();

        $errorTypes = [];
        foreach ($failures as $failure) {
            $type = $failure->exception_message ? 'exception' : 'validation';
            $errorTypes[$type] = ($errorTypes[$type] ?? 0) + 1;
        }

        return [
            'total_failures' => $failures->count(),
            'error_types' => $errorTypes,
        ];
    }
}
