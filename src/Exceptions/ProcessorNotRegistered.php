<?php

namespace Umutcangungormus\LaravelImportExport\Exceptions;

use RuntimeException;

class ProcessorNotRegistered extends RuntimeException
{
    public static function forModel(string $modelClass): self
    {
        return new self("No import processor is registered for [{$modelClass}]. Bind one via config('import-export.models.{$modelClass}.processor').");
    }
}
