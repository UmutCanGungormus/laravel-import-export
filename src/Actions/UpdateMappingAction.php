<?php

namespace Umutcangungormus\LaravelImportExport\Actions;

use Umutcangungormus\LaravelImportExport\Data\UpdateMappingData;
use Umutcangungormus\LaravelImportExport\Enums\MatchMethod;
use Umutcangungormus\LaravelImportExport\Models\ImportColumnMapping;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

class UpdateMappingAction
{
    public function execute(ImportSession $session, UpdateMappingData $data): ImportColumnMapping
    {
        $mapping = $session->columnMappings()->firstOrNew(
            ['source_column' => $data->source_column],
        );

        $mapping->fill([
            'target_field' => $data->target_field,
            'is_confirmed' => $data->confirmed,
            'match_method' => MatchMethod::Manual->value,
            'confidence_score' => $data->confirmed ? 1.0 : 0.0,
            'is_required' => $data->target_field
                ? $this->isFieldRequired($session->importable_type, $data->target_field)
                : false,
            'transformation_rules' => $this->rulesWithStrategy($mapping, $data),
        ]);

        $mapping->save();

        return $mapping;
    }

    /**
     * Folds the payload's combine strategy into the mapping's existing rules.
     *
     * The rules column is shared, so the strategy is written as one key rather
     * than replacing the object. A released mapping — one pointing at nothing —
     * drops the key: it no longer feeds a target, and a stale strategy left
     * behind would decide for whichever target the column is pointed at next.
     *
     * @param  ImportColumnMapping  $mapping  The row about to be written
     * @param  UpdateMappingData  $data  The incoming payload
     * @return ?array<string, mixed> The rules to store, or null when none remain
     */
    private function rulesWithStrategy(ImportColumnMapping $mapping, UpdateMappingData $data): ?array
    {
        $rules = (array) ($mapping->transformation_rules ?? []);

        if ($data->target_field === null || $data->multi_strategy === null) {
            unset($rules['multi_strategy']);
        } else {
            $rules['multi_strategy'] = $data->multi_strategy;
        }

        return $rules === [] ? null : $rules;
    }

    /** @param UpdateMappingData[] $dtos */
    public function executeBatch(ImportSession $session, array $dtos): void
    {
        foreach ($dtos as $dto) {
            $this->execute($session, $dto);
        }
    }

    private function isFieldRequired(string $modelClass, string $field): bool
    {
        if (! method_exists($modelClass, 'getImportableFields')) {
            return false;
        }

        return (bool) ($modelClass::getImportableFields()[$field]['required'] ?? false);
    }
}
