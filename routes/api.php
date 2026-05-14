<?php

use Illuminate\Support\Facades\Route;
use Umutcangungormus\LaravelImportExport\Http\Controllers\ImportMappingController;
use Umutcangungormus\LaravelImportExport\Http\Controllers\ImportSessionController;
use Umutcangungormus\LaravelImportExport\Http\Controllers\ImportTemplateController;

/*
|--------------------------------------------------------------------------
| Import / Export API routes
|--------------------------------------------------------------------------
|
| Opt-in via `config('import-export.routes.enabled') === true`. The
| ServiceProvider already wraps this file in the configured prefix +
| middleware groups, so this file only declares relative paths.
*/

Route::get('sessions', [ImportSessionController::class, 'index'])->name('import-export.sessions.index');
Route::post('sessions', [ImportSessionController::class, 'store'])->name('import-export.sessions.store');
Route::get('sessions/{id}', [ImportSessionController::class, 'show'])->whereNumber('id')->name('import-export.sessions.show');
Route::post('sessions/{id}/start', [ImportSessionController::class, 'start'])->whereNumber('id')->name('import-export.sessions.start');
Route::delete('sessions/{id}', [ImportSessionController::class, 'destroy'])->whereNumber('id')->name('import-export.sessions.cancel');
Route::get('sessions/{id}/progress', [ImportSessionController::class, 'progress'])->whereNumber('id')->name('import-export.sessions.progress');
Route::get('sessions/{id}/failures', [ImportSessionController::class, 'failuresSummary'])->whereNumber('id')->name('import-export.sessions.failures');
Route::get('sessions/{id}/failures/export', [ImportSessionController::class, 'exportFailures'])->whereNumber('id')->name('import-export.sessions.failures.export');

Route::get('sessions/{id}/mappings', [ImportSessionController::class, 'mappings'])->whereNumber('id')->name('import-export.mappings.index');
Route::put('sessions/{id}/mappings', [ImportMappingController::class, 'update'])->whereNumber('id')->name('import-export.mappings.update');
Route::get('sessions/{id}/mappings/suggestions', [ImportMappingController::class, 'suggestions'])->whereNumber('id')->name('import-export.mappings.suggestions');

Route::get('templates', [ImportTemplateController::class, 'index'])->name('import-export.templates.index');
Route::post('templates', [ImportTemplateController::class, 'store'])->name('import-export.templates.store');
Route::get('templates/{id}', [ImportTemplateController::class, 'show'])->whereNumber('id')->name('import-export.templates.show');
Route::put('templates/{id}', [ImportTemplateController::class, 'update'])->whereNumber('id')->name('import-export.templates.update');
Route::delete('templates/{id}', [ImportTemplateController::class, 'destroy'])->whereNumber('id')->name('import-export.templates.destroy');
