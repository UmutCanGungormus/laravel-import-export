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

    /*
     * Optional post-batch lifecycle hook (kept OUT of the interface signature
     * to stay backward compatible with existing host processors):
     *
     *   public function afterComplete(ImportSession $importSession): void;
     *
     * FinalizeImportJob calls this once, after every row in the import has
     * been persisted, guarding with method_exists(). This is where
     * order-independent resolution belongs — e.g. linking self-referential
     * foreign keys (manager_id, parent_id, category trees) whose target rows
     * may appear later in the file than the rows that reference them, so
     * per-row resolution during the streaming pass cannot work.
     */
}
