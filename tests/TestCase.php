<?php

namespace Umutcangungormus\LaravelImportExport\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Umutcangungormus\LaravelImportExport\ImportExportServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ImportExportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $app['config']->set('queue.default', 'sync');
        // Disable bundled users FK in tests — host's `users` table is not
        // guaranteed to exist in Testbench.
        $app['config']->set('import-export.foreign_keys.users', false);
        $app['config']->set('import-export.routes.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
