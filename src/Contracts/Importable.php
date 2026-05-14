<?php

namespace Umutcangungormus\LaravelImportExport\Contracts;

use Umutcangungormus\LaravelImportExport\Models\ImportSession;

/**
 * Contract for Eloquent models that participate in the import pipeline.
 *
 * Use {@see \Umutcangungormus\LaravelImportExport\Support\HasImportExport}
 * to get a config-driven default implementation.
 */
interface Importable
{
    /**
     * Return the fields that can be imported for this model.
     *
     * Each key is the target field name, and the value is an array with:
     *   - label:      Human-readable column name
     *   - required:   bool
     *   - type:       string | integer | decimal | date | boolean
     *   - aliases:    array of alternative column names for fuzzy matching
     *   - validation: array of Laravel validation rules
     *   - transform:  optional callable to transform raw value
     *   - default:    optional default value when column is missing
     */
    public static function getImportableFields(): array;

    /**
     * Return the field(s) used to identify an existing record for updateOrCreate.
     * If null or empty, a new record will always be created (insert-only).
     *
     * @return string[]|null
     */
    public static function getImportUniqueBy(): ?array;

    /**
     * Optionally transform the full row array before individual field processing.
     * The ImportSession carries contextual data (tenant id, options, etc.)
     */
    public static function prepareForImport(ImportSession $importSession, array $data): array;

    /**
     * Hook called after each row is successfully saved.
     */
    public static function afterImport(Importable $model, array $data): void;
}
