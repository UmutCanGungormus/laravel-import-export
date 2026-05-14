<?php

namespace Umutcangungormus\LaravelImportExport\Contracts;

use Umutcangungormus\LaravelImportExport\Models\ImportSession;

/**
 * Host applications implement this interface to plug a domain-specific
 * row transformer / persistence hook into the import pipeline.
 *
 * Processors are wired up via config('import-export.models.*.processor').
 */
interface ImportProcessorInterface
{
    /**
     * Transform a raw import row before the model is saved.
     *
     * The ImportSession carries contextual data (tenant id, options, etc.)
     * so the file itself does not need to contain those columns.
     */
    public function prepare(ImportSession $importSession, array $data): array;

    /**
     * Hook called after each row is successfully saved.
     *
     * The $model is always an Eloquent Model that also implements Importable.
     * Typed as object to avoid intersection-type constraints across PHP versions.
     */
    public function after(object $model, array $data): void;
}
