<?php

namespace Umutcangungormus\LaravelImportExport\Data;

readonly class CreateTemplateData
{
    public function __construct(
        public string $model_class,
        public string $template_name,
        public array $template_data,
        public ?string $description,
        public bool $is_default,
        public bool $is_company_wide,
    ) {}
}
