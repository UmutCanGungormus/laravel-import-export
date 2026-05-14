<?php

namespace Umutcangungormus\LaravelImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Umutcangungormus\LaravelImportExport\Enums\MatchMethod;

/**
 * @property int $id
 * @property int $import_session_id
 * @property string $source_column
 * @property string|null $target_field
 * @property float $confidence_score
 * @property MatchMethod $match_method
 * @property array|null $transformation_rules
 * @property bool $is_required
 * @property bool $is_confirmed
 * @property-read ImportSession $session
 *
 * @mixin \Eloquent
 */
class ImportColumnMapping extends Model
{
    protected $fillable = [
        'import_session_id',
        'source_column',
        'target_field',
        'confidence_score',
        'match_method',
        'transformation_rules',
        'is_required',
        'is_confirmed',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'match_method' => MatchMethod::class,
        'transformation_rules' => 'array',
        'is_required' => 'boolean',
        'is_confirmed' => 'boolean',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('import-export.tables.column_mappings', 'import_column_mappings');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }
}
