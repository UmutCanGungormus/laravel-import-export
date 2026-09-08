<?php

namespace Umutcangungormus\LaravelImportExport\Tests\Fixtures;

use Umutcangungormus\LaravelImportExport\Contracts\ImportProcessorInterface;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

/**
 * Test-only processor: collects all prepared rows + after-hook payloads
 * so assertions can verify the full import pipeline end-to-end.
 */
class FakeImportProcessor implements ImportProcessorInterface
{
    public static array $preparedRows = [];

    public static array $afterRows = [];

    public static bool $throwOnRow2 = false;

    /** @var ImportSession[] One entry per afterComplete() call, so double-finalize is visible. */
    public static array $completedSessions = [];

    public static function reset(): void
    {
        self::$preparedRows = [];
        self::$afterRows = [];
        self::$throwOnRow2 = false;
        self::$completedSessions = [];
    }

    public function prepare(ImportSession $importSession, array $data): array
    {
        self::$preparedRows[] = $data;

        if (self::$throwOnRow2 && count(self::$preparedRows) === 2) {
            throw new \RuntimeException('Synthetic row-2 failure.');
        }

        return $data;
    }

    public function after(object $model, array $data): void
    {
        self::$afterRows[] = ['model' => $model, 'data' => $data];
    }

    public function afterComplete(ImportSession $importSession): void
    {
        self::$completedSessions[] = $importSession;
    }
}
