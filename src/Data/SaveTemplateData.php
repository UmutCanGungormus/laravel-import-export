<?php

namespace Umutcangungormus\LaravelImportExport\Data;

readonly class SaveTemplateData
{
    public function __construct(
        public string $template_name,
        public ?string $description,
        public bool $is_default,
    ) {}
}
