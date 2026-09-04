<?php

namespace Umutcangungormus\LaravelImportExport\Data;

readonly class UpdateMappingData
{
    /**
     * @param  string  $source_column  The file header this mapping is keyed by
     * @param  ?string  $target_field  The target it feeds, or null to release it
     * @param  bool  $confirmed  Whether the user settled this row
     * @param  ?string  $multi_strategy  `merge`/`json` when several columns feed the target
     */
    public function __construct(
        public string $source_column,
        public ?string $target_field,
        public bool $confirmed,
        public ?string $multi_strategy = null,
    ) {}
}
