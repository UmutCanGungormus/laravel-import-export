<?php

namespace Umutcangungormus\LaravelImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|string|null $tenant_id
 * @property string $importable_type
 * @property string $file_name
 * @property string $file_path
 * @property string $file_disk
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $successful_rows
 * @property int $failed_rows
 * @property array|null $detected_headers
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @mixin \Eloquent
 */
class ImportSession extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'importable_type',
        'file_name',
        'file_path',
        'file_disk',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'detected_headers',
        'options',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => ImportStatus::class,
        'detected_headers' => 'array',
        'options' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('import-export.tables.sessions', 'import_sessions');
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function columnMappings(): HasMany
    {
        return $this->hasMany(ImportColumnMapping::class, 'import_session_id');
    }

    public function confirmedMappings(): HasMany
    {
        return $this->hasMany(ImportColumnMapping::class, 'import_session_id')
            ->where('is_confirmed', true);
    }

    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class, 'import_session_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function progressPercentage(): float
    {
        if ($this->total_rows === 0) {
            return 0.0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function markAs(ImportStatus $status): void
    {
        $this->update(['status' => $status]);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markAsCompletedWithErrors(): void
    {
        $this->update([
            'status' => ImportStatus::CompletedWithErrors,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => ImportStatus::Failed,
            'completed_at' => now(),
        ]);
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }
}
