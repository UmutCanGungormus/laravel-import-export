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
        return $session->columnMappings()->updateOrCreate(
            ['source_column' => $data->source_column],
            [
                'target_field' => $data->target_field,
                'is_confirmed' => $data->confirmed,
                'match_method' => MatchMethod::Manual->value,
                'confidence_score' => $data->confirmed ? 1.0 : 0.0,
                'is_required' => $data->target_field
                    ? $this->isFieldRequired($session->importable_type, $data->target_field)
                    : false,
            ],
        );
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
