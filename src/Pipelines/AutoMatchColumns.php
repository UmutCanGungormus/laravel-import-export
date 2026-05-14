<?php

namespace Umutcangungormus\LaravelImportExport\Pipelines;

use Closure;
use Umutcangungormus\LaravelImportExport\Contracts\ColumnMatcherContract;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;

/**
 * Runs auto-matching on the detected headers against the model's importable
 * fields, creating an ImportColumnMapping per source column.
 */
class AutoMatchColumns
{
    public function __construct(
        private ColumnMatcherContract $columnMatcher,
    ) {}

    public function handle(array $payload, Closure $next): mixed
    {
        $session = $payload['session'];
        $headers = $payload['headers'];
        $modelClass = $session->importable_type;

        if (! method_exists($modelClass, 'getImportableFields')) {
            return $next($payload);
        }

        $importableFields = $modelClass::getImportableFields();
        $autoConfirmThreshold = config('import-export.column_matching.auto_confirm_threshold', 0.8);

        $proposals = $this->columnMatcher->match($headers, $importableFields);

        foreach ($proposals as $proposal) {
            $targetField = $proposal['target_field'];
            $isRequired = $targetField ? ($importableFields[$targetField]['required'] ?? false) : false;
            $isConfirmed = $proposal['confidence_score'] >= $autoConfirmThreshold && $targetField !== null;

            $session->columnMappings()->updateOrCreate(
                ['source_column' => $proposal['source_column']],
                [
                    'target_field' => $targetField,
                    'confidence_score' => $proposal['confidence_score'],
                    'match_method' => $proposal['match_method'],
                    'is_required' => $isRequired,
                    'is_confirmed' => $isConfirmed,
                ],
            );
        }

        $session->markAs(ImportStatus::Mapping);
        $payload['proposals'] = $proposals;

        return $next($payload);
    }
}
