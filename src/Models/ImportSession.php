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

        // Clamp at 100%: concurrent chunk increments can transiently push
        // processed_rows past total_rows before counts settle.
        return round((min($this->processed_rows, $this->total_rows) / $this->total_rows) * 100, 2);
    }

    public function markAs(ImportStatus $status): void
    {
        $this->update(['status' => $status]);
    }

    public function markAsProcessing(): void
    {
        // Reset per-run progress counters and clear stale failures so a
        // retried/re-dispatched import reprocesses from a clean slate instead
        // of accumulating on top of a previous attempt (which made
        // processed_rows overshoot total_rows and the import never finish).
        $this->failures()->delete();

        $this->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
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

    /**
     * Confirmed source-column → target-field map, de-duplicated by target.
     *
     * When several source columns fuzzily match the same target field, the
     * naive confirmedMappings()->keyBy('source_column')->map(target_field)
     * is last-write-wins: the wrong source can silently claim the target.
     * Keep only the highest-confidence source per target (ties resolved by
     * iteration order) so near-duplicate headers don't corrupt the mapping.
     *
     * @return array<string,string> source_column => target_field
     */
    public function confirmedMappingLookup(): array
    {
        $lookup = [];
        $claimed = [];

        $mappings = $this->confirmedMappings()
            ->whereNotNull('target_field')
            ->orderByDesc('confidence_score')
            ->get();

        foreach ($mappings as $mapping) {
            if (isset($claimed[$mapping->target_field])) {
                continue; // a higher-confidence source already owns this target
            }

            $claimed[$mapping->target_field] = true;
            $lookup[$mapping->source_column] = $mapping->target_field;
        }

        return $lookup;
    }
}
