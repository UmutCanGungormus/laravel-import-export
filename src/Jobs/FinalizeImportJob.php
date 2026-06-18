<?php

namespace Umutcangungormus\LaravelImportExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

/**
 * Runs once, after every ProcessImportChunkJob in the batch has succeeded.
 *
 * Invokes the optional processor-level afterComplete() hook — the place for
 * order-independent resolution (linking self-referential foreign keys such as
 * manager_id / parent_id whose target rows may appear later in the file than
 * the rows that reference them, so per-row resolution cannot work) — then
 * marks the session Completed (or CompletedWithErrors when any row failed).
 */
class FinalizeImportJob implements ShouldQueue
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

    public function handle(): void
    {
        $session = ImportSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        $modelClass = $session->importable_type;

        // Resolve the processor through the config registry (same lookup as
        // the chunk jobs) and run its optional post-batch pass. Guarded with
        // method_exists() so the afterComplete() hook stays optional and the
        // ImportProcessorInterface remains backward compatible.
        $processorClass = config('import-export.models.'.$modelClass.'.processor');

        if ($processorClass && class_exists($processorClass)) {
            $processor = app($processorClass);

            if (method_exists($processor, 'afterComplete')) {
                $processor->afterComplete($session);
            }
        }

        $session->refresh();

        if ($session->failed_rows > 0) {
            $session->markAsCompletedWithErrors();
        } else {
            $session->markAsCompleted();
        }
    }

    public function failed(Throwable $exception): void
    {
        ImportSession::find($this->sessionId)?->markAsFailed();
    }
}
