<?php

namespace Umutcangungormus\LaravelImportExport\Data;

readonly class InitializeImportData
{
    public function __construct(
        public string $model_class,
        public string $file_path,
        public string $file_name,
        public string $file_disk,
        public int|string|null $tenant_id,
        public int $header_row,
        public int $chunk_size,
    ) {}
}
