<?php

namespace Umutcangungormus\LaravelImportExport\Contracts;

use Umutcangungormus\LaravelImportExport\Models\ImportSession;

interface FailureHandlerContract
{
    /**
     * Record a failed row for a given import session.
     */
    public function record(
        ImportSession $session,
        int $rowNumber,
        array $rowData,
        array $errors,
        ?string $exceptionMessage = null,
    ): void;

    /**
     * Export all failures for a session as a downloadable file response.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function export(ImportSession $session): mixed;

    /**
     * Aggregate this session's failures into a summary payload used by the
     * progress / failures-summary HTTP endpoints. Implementations must at
     * minimum return:
     *
     *   - total_failures: int
     *   - error_types: array<string, int>  (bucketed counts)
     */
    public function summary(ImportSession $session): array;
}
