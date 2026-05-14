<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Umutcangungormus\LaravelImportExport\Contracts\Exportable;

/**
 * Streams model data as CSV. Relies on the model's getExportableFields() definition.
 */
class ModelExportService
{
    /**
     * Export all records of the given model class as a streamed CSV download.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model&Exportable>  $modelClass
     */
    public function export(string $modelClass, ?callable $scope = null): StreamedResponse
    {
        if (! method_exists($modelClass, 'getExportableFields')) {
            throw new \InvalidArgumentException("{$modelClass} does not implement getExportableFields().");
        }

        $fields = $modelClass::getExportableFields();
        $headers = array_column($fields, 'label');

        $chunkSize = config('import-export.export.chunk_size', 500);
        $filename = class_basename($modelClass).'_export_'.now()->format('Y_m_d_His').'.csv';

        return response()->streamDownload(function () use ($modelClass, $headers, $fields, $chunkSize, $scope) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8

            fputcsv($handle, $headers);

            $query = $modelClass::query();
            $query = $modelClass::modifyExportQuery($query);

            if ($scope) {
                $query = $scope($query) ?? $query;
            }

            $query->chunk($chunkSize, function ($records) use ($handle, $fields) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($fields as $fieldConfig) {
                        $value = data_get($record, $fieldConfig['accessor']);
                        $row[] = $this->formatValue($value, $fieldConfig['format'] ?? null);
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatValue(mixed $value, ?string $format): mixed
    {
        if ($value === null) {
            return '';
        }

        return match ($format) {
            'date' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d') : $value,
            'datetime' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d H:i:s') : $value,
            'currency' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'boolean' => $value ? __('import-export::export.yes') : __('import-export::export.no'),
            default => $value,
        };
    }
}
