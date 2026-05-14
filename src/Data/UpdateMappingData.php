<?php

namespace Umutcangungormus\LaravelImportExport\Data;

readonly class UpdateMappingData
{
    public function __construct(
        public string $source_column,
        public ?string $target_field,
        public bool $confirmed,
    ) {}
}
