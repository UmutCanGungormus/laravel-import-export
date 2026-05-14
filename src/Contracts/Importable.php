<?php

namespace Umutcangungormus\LaravelImportExport\Contracts;

/**
 * Contract for Eloquent models that participate in the import pipeline.
 *
 * The package's row-level lifecycle hooks live on
 * {@see ImportProcessorInterface} (the documented public extension point).
 * Model-level concerns are limited to declaring which fields are importable
 * and how rows are identified for updateOrCreate.
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
}
