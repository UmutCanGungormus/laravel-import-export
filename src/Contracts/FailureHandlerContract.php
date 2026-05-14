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
}
