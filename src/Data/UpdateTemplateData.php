<?php

namespace Umutcangungormus\LaravelImportExport\Data;

readonly class UpdateTemplateData
{
    public function __construct(
        public ?string $template_name,
        public ?string $description,
        public ?bool $is_default,
        public ?bool $is_company_wide,
    ) {}
}
