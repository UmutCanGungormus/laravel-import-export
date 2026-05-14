<?php

namespace Umutcangungormus\LaravelImportExport\Pipelines;

use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Ensures all required fields have a confirmed mapping before processing starts.
 * Throws ValidationException with a list of missing fields if any required
 * field is unconfirmed or unmapped.
 */
class ValidateRequiredMappings
{
    public function handle(array $payload, Closure $next): mixed
    {
        $session = $payload['session'];
        $modelClass = $session->importable_type;

        if (! method_exists($modelClass, 'getImportableFields')) {
            return $next($payload);
        }

        $importableFields = $modelClass::getImportableFields();
        $requiredFields = array_keys(array_filter($importableFields, fn ($f) => $f['required'] ?? false));

        $confirmedTargets = $session->confirmedMappings()
            ->whereNotNull('target_field')
            ->pluck('target_field')
            ->all();

        $missing = array_values(array_diff($requiredFields, $confirmedTargets));

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'mappings' => [
                    trans('import-export::mapping.required_missing', ['fields' => implode(', ', $missing)]),
                ],
            ]);
        }

        return $next($payload);
    }
}
