<?php

namespace Umutcangungormus\LaravelImportExport\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;
use Umutcangungormus\LaravelImportExport\Enums\ImportStatus;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

/**
 * Planner job. Splits the import into a Bus batch of ProcessImportChunkJob —
 * one per config('import-export.batch_size') data rows — with
 * FinalizeImportJob as the then() callback. Each chunk job is short, isolated,
 * and gives real progress; the post-batch pass runs once in the finalizer.
 *
 * Kept under the original ProcessImportJob class name so StartImportAction and
 * existing dispatch sites need no change.
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

    public function handle(): void
    {
        $session = ImportSession::find($this->sessionId);

        if (! $session || $session->status === ImportStatus::Cancelled) {
            return;
        }

        $session->markAsProcessing();

        $total = (int) ($session->total_rows ?? 0);

        if ($total <= 0) {
            // Empty file or count missing — nothing to chunk; finalize directly.
            FinalizeImportJob::dispatch($session->id);

            return;
        }

        $batchSize = max(1, (int) config('import-export.batch_size', 500));
        $connection = config('import-export.queue.connection');
        $queue = config('import-export.queue.queue', 'default');

        $jobs = [];
        for ($start = 1; $start <= $total; $start += $batchSize) {
            $limit = min($batchSize, $total - $start + 1);
            $jobs[] = new ProcessImportChunkJob($session->id, $start, $limit);
        }

        $sessionId = $session->id;

        $batch = Bus::batch($jobs)
            ->name("import:{$sessionId}")
            ->onQueue($queue)
            ->then(function (Batch $batch) use ($sessionId) {
                FinalizeImportJob::dispatch($sessionId);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($sessionId) {
                ImportSession::find($sessionId)?->markAsFailed();
            });

        if ($connection) {
            $batch->onConnection($connection);
        }

        $batch->dispatch();
    }

    public function failed(Throwable $exception): void
    {
        ImportSession::find($this->sessionId)?->markAsFailed();
    }
}
