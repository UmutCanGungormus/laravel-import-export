<?php

namespace Umutcangungormus\LaravelImportExport\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Mapping = 'mapping';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Mapping => 'Mapping',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with errors',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::CompletedWithErrors,
            self::Failed,
            self::Cancelled,
        ], true);
    }
}
