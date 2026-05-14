<?php

use Illuminate\Support\Facades\Route;

it('does not register any package routes when routes.enabled is false', function () {
    // Default config sets routes.enabled = false.
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'import-export.'));

    expect($routes)->toBeEmpty();

    $response = $this->get('/api/import-export/sessions');
    $response->assertNotFound();
});

it('registers the package routes when routes.enabled is true and re-booting', function () {
    config()->set('import-export.routes.enabled', true);

    // Re-register routes via a fresh ServiceProvider boot pass.
    $provider = new \Umutcangungormus\LaravelImportExport\ImportExportServiceProvider($this->app);
    $provider->boot();

    $named = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'import-export.'));

    expect($named->count())->toBeGreaterThan(5);

    // Hitting the index route should NOT 404 anymore (it'll return 200/401/500
    // depending on db state but not 404).
    $response = $this->get('/api/import-export/sessions');
    expect($response->status())->not->toBe(404);
});

it('exposes gate ability names from config without binding them', function () {
    $abilities = (array) config('import-export.gates');

    expect($abilities)->toHaveKey('session_create');
    expect($abilities['session_create'])->toBeString();

    // The package must NOT call Gate::define for any of these names.
    foreach ($abilities as $ability) {
        expect(\Illuminate\Support\Facades\Gate::has($ability))
            ->toBeFalse("Package should not bind gate ability '{$ability}' — host's responsibility.");
    }
});
