<?php

namespace Umutcangungormus\LaravelImportExport;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Umutcangungormus\LaravelImportExport\Tenancy\NullTenantResolver;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/import-export.php', 'import-export');

        // Tenancy
        $this->app->bind(TenantResolverContract::class, function (Application $app) {
            $class = config('import-export.tenancy.resolver', NullTenantResolver::class);

            return $app->make($class);
        });

        // Contract → concrete bindings are added in Task 2 once the services
        // are ported. The container will throw an explicit error if a host
        // resolves one of them before the service classes are in place.
        $this->registerDomainBindings();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'import-export');

        $this->registerPublishables();
        $this->registerRoutes();
    }

    /**
     * Bind domain-level contracts. Filled out in Task 2; this method is
     * deliberately a stub today so callers can resolve `TenantResolverContract`
     * end-to-end without booting half-implemented services.
     */
    protected function registerDomainBindings(): void
    {
        $bindings = [
            \Umutcangungormus\LaravelImportExport\Contracts\ColumnMatcherContract::class
                => \Umutcangungormus\LaravelImportExport\Services\ColumnMatcherService::class,
            \Umutcangungormus\LaravelImportExport\Contracts\FailureHandlerContract::class
                => \Umutcangungormus\LaravelImportExport\Services\FailureHandlerService::class,
        ];

        foreach ($bindings as $contract => $concrete) {
            if (class_exists($concrete) && interface_exists($contract)) {
                $this->app->bind($contract, $concrete);
            }
        }
    }

    protected function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/import-export.php' => config_path('import-export.php'),
        ], ['import-export-config', 'import-export']);

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['import-export-migrations', 'import-export']);

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/import-export'),
        ], ['import-export-lang', 'import-export']);
    }

    protected function registerRoutes(): void
    {
        if (! (bool) config('import-export.routes.enabled', false)) {
            return;
        }

        $routes = __DIR__.'/../routes/api.php';

        if (! file_exists($routes)) {
            return;
        }

        Route::middleware((array) config('import-export.routes.middleware', ['api']))
            ->prefix((string) config('import-export.routes.prefix', 'api/import-export'))
            ->group($routes);
    }
}
