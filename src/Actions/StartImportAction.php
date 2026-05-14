<?php

namespace Umutcangungormus\LaravelImportExport\Actions;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Exceptions\ProcessorNotRegistered;
use Umutcangungormus\LaravelImportExport\Jobs\ProcessImportJob;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;
use Umutcangungormus\LaravelImportExport\Pipelines\ValidateRequiredMappings;

class StartImportAction
{
    public function __construct(
        private Pipeline $pipeline,
    ) {}

    public function execute(ImportSession $session, bool $dispatch = true): ImportSession
    {
        if ($session->status === ImportStatus::Processing) {
            throw new \RuntimeException('Import is already processing.');
        }

        // Verify the host has registered a processor for this model.
        $processorClass = config('import-export.models.'.$session->importable_type.'.processor');
        if (! $processorClass || ! class_exists($processorClass)) {
            throw ProcessorNotRegistered::forModel($session->importable_type);
        }

        // Remove unconfirmed mappings with no target — they are noise.
        $session->columnMappings()
            ->where('is_confirmed', false)
            ->whereNull('target_field')
            ->delete();

        $this->pipeline
            ->send(['session' => $session])
            ->through([ValidateRequiredMappings::class])
            ->thenReturn();

        if ($dispatch) {
            DB::afterCommit(function () use ($session) {
                ProcessImportJob::dispatch($session->id);
            });
        }

        // Optimistically mark as processing (Job re-asserts when it starts).
        $session->update(['status' => ImportStatus::Processing]);

        return $session->fresh();
    }
}
