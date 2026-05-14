<?php

namespace Umutcangungormus\LaravelImportExport\Contracts;

interface Exportable
{
    /**
     * Return the fields that can be exported for this model.
     *
     * Each key is the field identifier, and the value is an array with:
     *   - label:    Column header in the exported file
     *   - accessor: Dot-notation property path (supports relations: 'department.name')
     *   - format:   optional formatter: 'date' | 'datetime' | 'currency' | 'boolean'
     */
    public static function getExportableFields(): array;

    /**
     * Optionally modify the export query (add eager loads, scopes, etc.).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function modifyExportQuery($query);
}
