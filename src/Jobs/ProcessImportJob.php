<?php

namespace Umutcangungormus\LaravelImportExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Exceptions\ProcessorNotRegistered;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Services\FileReaderService;

/**
 * Processes one ImportSession end-to-end:
 *   - reads the source file in chunks
 *   - delegates row-level transforms to the host's ImportProcessorInterface
 *   - persists, validates, and records failures
 *
 * No Horizon dependency: uses only Illuminate's queue contracts so any queue
 * driver (database, redis, sync, sqs, …) works.
 */
class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public readonly int $sessionId,
    ) {
        $this->timeout = (int) config('import-export.job_timeout', 600);
        $this->tries = (int) config('import-export.job_tries', 3);

        $connection = config('import-export.queue.connection');
        $queue = config('import-export.queue.queue', 'default');

        if ($connection) {
            $this->onConnection($connection);
        }
        $this->onQueue($queue);
    }

    public function handle(FileReaderService $fileReader, FailureHandlerContract $failureHandler): void
    {
        $session = ImportSession::findOrFail($this->sessionId);

        if ($session->status === ImportStatus::Cancelled) {
            return;
        }

        $session->markAsProcessing();

        try {
            $this->process($session, $fileReader, $failureHandler);

            $session->refresh();
            if ($session->failed_rows > 0) {
                $session->markAsCompletedWithErrors();
            } else {
                $session->markAsCompleted();
            }
        } catch (Throwable $e) {
            $session->markAsFailed();
            throw $e;
        }
    }

    private function process(
        ImportSession $session,
        FileReaderService $fileReader,
        FailureHandlerContract $failureHandler,
    ): void {
        $modelClass = $session->importable_type;
        $importableFields = method_exists($modelClass, 'getImportableFields')
            ? $modelClass::getImportableFields()
            : [];

        $uniqueBy = method_exists($modelClass, 'getImportUniqueBy')
            ? ($modelClass::getImportUniqueBy() ?? [])
            : [];

        // Resolve processor through config registry (single source of truth).
        $processorClass = config('import-export.models.'.$modelClass.'.processor');
        if (! $processorClass || ! class_exists($processorClass)) {
            throw ProcessorNotRegistered::forModel($modelClass);
        }
        $processor = app($processorClass);

        $mappingLookup = $session->confirmedMappings()
            ->whereNotNull('target_field')
            ->get()
            ->keyBy('source_column')
            ->map(fn ($m) => $m->target_field)
            ->all();

        $chunkSize = $session->getOption('chunk_size', config('import-export.chunk_size', 1000));
        $headerRow = $session->getOption('header_row', 1);
        $headers = $session->detected_headers ?? [];

        $fileReader->readChunks(
            $session->file_path,
            $session->file_disk,
            $headers,
            $headerRow,
            $chunkSize,
            function (array $chunk) use ($session, $modelClass, $importableFields, $mappingLookup, $uniqueBy, $failureHandler, $processor) {
                foreach ($chunk as $item) {
                    $this->processRow(
                        $session,
                        $modelClass,
                        $importableFields,
                        $mappingLookup,
                        $uniqueBy,
                        $item['row_number'],
                        $item['data'],
                        $failureHandler,
                        $processor,
                    );
                }
            },
        );
    }

    private function processRow(
        ImportSession $session,
        string $modelClass,
        array $importableFields,
        array $mappingLookup,
        array $uniqueBy,
        int $rowNumber,
        array $rawData,
        FailureHandlerContract $failureHandler,
        object $processor,
    ): void {
        try {
            // 1. Transform raw row using mappings
            $mapped = $this->mapRow($rawData, $mappingLookup, $importableFields);

            // 2. Processor pre-processing hook
            $mapped = $processor->prepare($session, $mapped);

            // 3. Validate
            $validationRules = $this->buildValidationRules($importableFields);
            $validator = Validator::make($mapped, $validationRules);

            if ($validator->fails()) {
                $failureHandler->record($session, $rowNumber, $rawData, $validator->errors()->all());
                $session->increment('processed_rows');

                return;
            }

            $validated = $validator->validated();

            // 4. Persist inside transaction. afterImport receives the merged
            // shape (processor-prepared + validated) so processor-added fields
            // outside the validation rule set — like `_tenant_id` or resolved
            // foreign keys — survive into the after-hook.
            DB::transaction(function () use ($modelClass, $mapped, $validated, $uniqueBy, $session, $processor) {
                if (! empty($uniqueBy)) {
                    $lookupKeys = array_intersect_key($validated, array_flip($uniqueBy));
                    $fillValues = array_diff_key($validated, array_flip($uniqueBy));

                    $model = forward_static_call([$modelClass, 'updateOrCreate'], $lookupKeys, $fillValues);
                } else {
                    $model = forward_static_call([$modelClass, 'create'], $validated);
                }

                $processor->after($model, array_merge($mapped, $validated));

                $session->increment('successful_rows');
            });
        } catch (Throwable $e) {
            $failureHandler->record($session, $rowNumber, $rawData, [], $e->getMessage());
        }

        $session->increment('processed_rows');
    }

    private function mapRow(array $rawData, array $mappingLookup, array $importableFields): array
    {
        $mapped = [];

        foreach ($mappingLookup as $sourceColumn => $targetField) {
            $rawValue = $rawData[$sourceColumn] ?? null;

            $transform = $importableFields[$targetField]['transform'] ?? null;
            $mapped[$targetField] = $transform && $rawValue !== null ? $transform($rawValue) : $rawValue;
        }

        foreach ($importableFields as $fieldKey => $fieldConfig) {
            if (! array_key_exists($fieldKey, $mapped) && array_key_exists('default', $fieldConfig)) {
                $mapped[$fieldKey] = $fieldConfig['default'];
            }
        }

        return $mapped;
    }

    private function buildValidationRules(array $importableFields): array
    {
        $rules = [];

        foreach ($importableFields as $fieldKey => $fieldConfig) {
            if (! empty($fieldConfig['validation'])) {
                $rules[$fieldKey] = $fieldConfig['validation'];
            }
        }

        return $rules;
    }

    public function failed(Throwable $exception): void
    {
        $session = ImportSession::find($this->sessionId);
        $session?->markAsFailed();
    }
}
