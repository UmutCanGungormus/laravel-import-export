<?php

namespace Umutcangungormus\LaravelImportExport\Enums;

enum MatchMethod: string
{
    case Exact = 'exact';
    case Label = 'label';
    case Alias = 'alias';
    case Fuzzy = 'fuzzy';
    case Template = 'template';
    case Manual = 'manual';
    case None = 'none';
}
