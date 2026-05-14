<?php

namespace Umutcangungormus\LaravelImportExport\Contracts;

interface ColumnMatcherContract
{
    /**
     * Match detected file headers against model importable fields.
     *
     * Returns an array of mapping proposals:
     *   [
     *     'source_column'    => string,
     *     'target_field'     => string|null,
     *     'confidence_score' => float (0-1),
     *     'match_method'     => 'exact'|'label'|'alias'|'fuzzy'|'none',
     *   ]
     */
    public function match(array $headers, array $importableFields): array;

    /**
     * Get ranked suggestions for a single source column.
     *
     * Returns array of ['field', 'label', 'confidence'] sorted desc by confidence.
     */
    public function suggest(string $sourceColumn, array $importableFields): array;
}
