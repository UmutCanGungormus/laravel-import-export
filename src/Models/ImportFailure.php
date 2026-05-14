<?php

namespace Umutcangungormus\LaravelImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $import_session_id
 * @property int $row_number
 * @property array $row_data
 * @property array|null $errors
 * @property string|null $exception_message
 * @property-read ImportSession $session
 *
 * @mixin \Eloquent
 */
class ImportFailure extends Model
{
    protected $fillable = [
        'import_session_id',
        'row_number',
        'row_data',
        'errors',
        'exception_message',
    ];

    protected $casts = [
        'row_data' => 'array',
        'errors' => 'array',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('import-export.tables.failures', 'import_failures');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }
}
