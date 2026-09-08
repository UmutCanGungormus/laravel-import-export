<?php

namespace Umutcangungormus\LaravelImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Enums\MultiColumnStrategy;

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
     * The one exception is a target the user deliberately fed from several
     * columns — a `multi` field carrying a {@see MultiColumnStrategy}. Those
     * keep every column; {@see \Umutcangungormus\LaravelImportExport\Jobs\ProcessImportChunkJob}
     * folds them into one value per row. Without a strategy nothing changes,
     * so a fuzzy double-match still resolves the historic way.
     *
     * @return array<string,string> source_column => target_field
     */
    public function confirmedMappingLookup(): array
    {
        $lookup = [];
        $claimed = [];
        $strategies = $this->multiColumnStrategies();

        $mappings = $this->confirmedMappings()
            ->whereNotNull('target_field')
            ->orderByDesc('confidence_score')
            ->get();

        foreach ($mappings as $mapping) {
            $target = (string) $mapping->target_field;

            if (isset($claimed[$target]) && ! isset($strategies[$target])) {
                continue; // a higher-confidence source already owns this target
            }

            $claimed[$target] = true;
            $lookup[$mapping->source_column] = $target;
        }

        return $lookup;
    }

    /**
     * The combine strategy each multi-fed target was given, keyed by target.
     *
     * Read off the mappings' `transformation_rules`; when the columns of one
     * target disagree, the oldest row — the column that claimed the target
     * first — decides. A target whose field config does not mark it `multi` is
     * ignored outright, so a stale rule cannot turn a date or an id column into
     * a merged string.
     *
     * @return array<string, MultiColumnStrategy> target_field => strategy
     */
    public function multiColumnStrategies(): array
    {
        $capable = $this->multiCapableTargets();

        if ($capable === []) {
            return [];
        }

        $strategies = [];

        $mappings = $this->confirmedMappings()
            ->whereNotNull('target_field')
            ->orderBy('id')
            ->get();

        foreach ($mappings as $mapping) {
            $target = (string) $mapping->target_field;

            if (isset($strategies[$target]) || ! isset($capable[$target])) {
                continue;
            }

            $strategy = MultiColumnStrategy::tryFromValue(
                data_get($mapping->transformation_rules, 'multi_strategy'),
            );

            if ($strategy !== null) {
                $strategies[$target] = $strategy;
            }
        }

        return $strategies;
    }

    /**
     * Target fields whose config allows more than one source column.
     *
     * @return array<string, true> Keyed by target field for isset() lookups
     */
    private function multiCapableTargets(): array
    {
        $modelClass = $this->importable_type;

        if (! is_string($modelClass) || ! method_exists($modelClass, 'getImportableFields')) {
            return [];
        }

        $capable = [];

        foreach ($modelClass::getImportableFields() as $field => $config) {
            if (! empty($config['multi'])) {
                $capable[$field] = true;
            }
        }

        return $capable;
    }
}
