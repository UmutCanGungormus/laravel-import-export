<div align="center">

# Laravel Import / Export

**Framework-grade, tenant-agnostic CSV/XLSX import & export pipeline for Laravel.**

Column auto-matching · reusable mapping templates · queued batch processing · per-row failure tracking · a publishable HTTP layer — all decoupled from your auth, tenancy, and domain models.

[![Tests](https://github.com/UmutCanGungormus/laravel-import-export/actions/workflows/tests.yml/badge.svg)](https://github.com/UmutCanGungormus/laravel-import-export/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/umutcangungormus/laravel-import-export.svg?style=flat-square)](https://packagist.org/packages/umutcangungormus/laravel-import-export)
[![PHP Version](https://img.shields.io/packagist/php-v/umutcangungormus/laravel-import-export.svg?style=flat-square)](https://packagist.org/packages/umutcangungormus/laravel-import-export)
[![Laravel](https://img.shields.io/badge/Laravel-11.x%20%7C%2012.x-FF2D20.svg?style=flat-square&logo=laravel)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/umutcangungormus/laravel-import-export.svg?style=flat-square)](LICENSE)

</div>

---

## Table of Contents

- [Why this package](#why-this-package)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quickstart](#quickstart)
- [Configuration](#configuration)
- [Core concepts](#core-concepts)
  - [The model registry](#the-model-registry)
  - [Processors](#processors)
  - [Tenancy](#tenancy)
  - [Authorization](#authorization)
- [Processing pipeline](#processing-pipeline)
- [HTTP API](#http-api)
- [Exporting](#exporting)
- [Localization](#localization)
- [Testing](#testing)
- [Frontend](#frontend)
- [Versioning](#versioning)
- [License](#license)

---

## Why this package

Most import/export solutions hard-wire themselves to your `User` model, your tenancy strategy, and your authorization stack — so they never quite leave the app they were born in. This package is the opposite: a **generic engine** whose every integration point is an interface you bind. Drop it into a single-tenant SaaS or a multi-tenant platform without touching its source.

It was extracted and generalized from a production multi-tenant platform, then hardened into a standalone library with a full [Pest](https://pestphp.com) suite.

## Features

- 📥 **CSV & XLSX import** — deterministic streaming reader with delimiter sniffing, BOM stripping, and UTF-8 normalization. No `maatwebsite/excel` dependency required.
- 🧠 **Automatic column matching** — fuzzy header-to-field matching (Levenshtein + `similar_text` + alias/label awareness) with confidence scoring and tunable thresholds.
- 🗂️ **Mapping templates** — save a session's column mapping and reuse it; per-user limits, defaults, and validation built in.
- ⚙️ **Queued batch processing** — a planner job fans the file into a `Bus` batch of short-lived chunk jobs, so large imports survive worker timeouts and report real progress.
- 🎯 **Per-row failure tracking** — every rejected row is recorded with its reason and exportable as a failures file.
- 🏢 **Tenant-agnostic** — a single `TenantResolverContract` injects your tenant id (company, workspace, org…) into every session. Defaults to single-tenant.
- 🔐 **Auth-stack agnostic** — the package only *names* gate abilities; you bind the rules (native Gates, `spatie/laravel-permission`, Bouncer, …).
- 🌐 **Opt-in HTTP layer** — a publishable controller/request/resource set with namespaced routes, or wire your own.
- 🌍 **i18n** — English & Turkish translations included and publishable.
- 🧩 **Configurable everything** — table names, disks, queue connection, FK constraints, thresholds.

## Requirements

| | Version |
|---|---|
| PHP | `^8.3` |
| Laravel | `11.x` · `12.x` |

## Installation

```bash
composer require umutcangungormus/laravel-import-export
```

The service provider is auto-discovered. Publish the config, migrations, and translations as needed:

```bash
# Config (required — this is where you register your models)
php artisan vendor:publish --tag=import-export-config

# Migrations
php artisan vendor:publish --tag=import-export-migrations

# Translations (optional)
php artisan vendor:publish --tag=import-export-lang

# …or everything at once
php artisan vendor:publish --tag=import-export
```

The queued batch pipeline relies on Laravel's job batching, which needs the `job_batches` table:

```bash
php artisan queue:batches-table
php artisan migrate
```

## Quickstart

**1. Make your model importable.**

```php
use Illuminate\Database\Eloquent\Model;
use Umutcangungormus\LaravelImportExport\Contracts\Exportable;
use Umutcangungormus\LaravelImportExport\Contracts\Importable;
use Umutcangungormus\LaravelImportExport\Support\HasImportExport;

class Product extends Model implements Importable, Exportable
{
    use HasImportExport; // config-driven field resolution — nothing else needed
}
```

**2. Describe it in `config/import-export.php`.**

```php
'models' => [
    App\Models\Product::class => [
        'unique_by'   => ['sku'],                              // updateOrCreate key
        'processor'   => App\Imports\ProductProcessor::class,  // optional
        'fields' => [
            'sku'   => ['required' => true,  'type' => 'string',  'aliases' => ['code', 'stok kodu'], 'validation' => ['required', 'string', 'max:64']],
            'name'  => ['required' => true,  'type' => 'string',  'validation' => ['required', 'string', 'max:255']],
            'price' => ['required' => false, 'type' => 'decimal', 'validation' => ['nullable', 'numeric']],
        ],
        'export_fields' => [
            'id'    => ['accessor' => 'id'],
            'sku'   => ['accessor' => 'sku'],
            'name'  => ['accessor' => 'name'],
            'price' => ['accessor' => 'price'],
        ],
    ],
],
```

**3. Run an import** — programmatically, or via the [HTTP API](#http-api):

```php
use Umutcangungormus\LaravelImportExport\Actions\ImportExport\InitializeImportAction;
use Umutcangungormus\LaravelImportExport\Actions\ImportExport\StartImportAction;
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;

// Upload → detect headers → auto-match columns
$session = app(InitializeImportAction::class)->handle(new InitializeImportData(
    model: App\Models\Product::class,
    file:  $request->file('file'),
));

// (optionally let the user adjust $session->mappings here, then…)

// Dispatch the queued batch pipeline
app(StartImportAction::class)->handle($session);
```

Progress, failures, and status are tracked on the `ImportSession` model throughout.

## Configuration

`config/import-export.php` is fully documented inline. Highlights:

| Key | Purpose | Default |
|---|---|---|
| `disk` / `storage_path` | Where uploaded source files live | `local` / `imports` |
| `tables.*` | Override table names (sessions, mappings, templates, failures, users) | package defaults |
| `foreign_keys.users` | Toggle the bundled `users` FK constraint | `true` |
| `batch_size` | Rows per queued chunk job | `500` |
| `job_timeout` / `job_tries` | Per-job limits | `600` / `3` |
| `queue.connection` / `queue.queue` | Where jobs run (Horizon-friendly) | app defaults |
| `column_matching.auto_confirm_threshold` | Score ≥ this → auto-confirmed | `0.8` |
| `column_matching.suggestion_threshold` | Score ≥ this → shown as a suggestion | `0.3` |
| `templates.*` | Enable/limit mapping templates | enabled, 50/user |
| `tenancy.resolver` | Your `TenantResolverContract` implementation | `NullTenantResolver` |
| `routes.enabled` / `prefix` / `middleware` | Opt-in HTTP layer | `false` / `api/import-export` / `['api']` |
| `gates.*` | Names of the authorization abilities to check | namespaced strings |
| `models` | The model registry (you fill this in) | empty |

> **Tip:** For large imports, point `queue.connection` at a dedicated connection whose `retry_after` exceeds `job_timeout`, and keep `job_tries` at `1` — a half-finished bulk import must never auto-retry and double-process rows.

## Core concepts

### The model registry

Rather than annotating models, you declare each importable model's schema once in `config('import-export.models')`, keyed by FQCN. Each entry defines `fields` (with `required`, `type`, `aliases`, `validation`, `transform`, `default`), the `unique_by` key for `updateOrCreate`, an optional `processor`, and `export_fields`. The `HasImportExport` trait reads this config so your models stay clean.

### Processors

Domain logic — value coercion, related-record resolution, side effects — lives in a processor implementing `ImportProcessorInterface`:

```php
use Umutcangungormus\LaravelImportExport\Contracts\ImportProcessorInterface;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

class ProductProcessor implements ImportProcessorInterface
{
    /** Transform a raw row before the model is saved. */
    public function prepare(ImportSession $session, array $data): array
    {
        $data['price'] = (float) str_replace(',', '.', $data['price'] ?? 0);

        return $data;
    }

    /** Hook after each row is persisted. */
    public function after(object $model, array $data): void
    {
        // e.g. attach tags, fire events…
    }

    /**
     * OPTIONAL, order-independent post-pass (called once, after every row).
     * Ideal for self-referential FKs (manager_id, parent_id, category trees)
     * whose targets may appear later in the file than the rows referencing them.
     */
    public function afterComplete(ImportSession $session): void
    {
        // resolve deferred relationships here
    }
}
```

`afterComplete()` is intentionally **not** part of the interface signature (so existing processors keep working) — `FinalizeImportJob` calls it via `method_exists()`.

### Tenancy

Bind a `TenantResolverContract` to scope every session to the active tenant:

```php
use Illuminate\Database\Eloquent\Builder;
use Umutcangungormus\LaravelImportExport\Tenancy\TenantResolverContract;

class CompanyTenantResolver implements TenantResolverContract
{
    public function currentTenantId(): int|string|null
    {
        return auth()->user()?->company_id;
    }

    public function scopeQuery(Builder $query): Builder
    {
        return $query->where('company_id', $this->currentTenantId());
    }
}
```

```php
// config/import-export.php
'tenancy' => ['resolver' => App\Support\CompanyTenantResolver::class],
```

The default `NullTenantResolver` returns `null` — perfect for single-tenant apps.

### Authorization

The package never assumes an auth stack; it only references **gate ability names** (configurable under `gates.*`). Define them however you like:

```php
Gate::define('import-export.session.create', fn ($user) => $user->can('manage-imports'));
```

## Processing pipeline

```
StartImportAction
   └─ ProcessImportJob (planner)
        ├─ splits the file into a Bus batch …
        ├─ ProcessImportChunkJob ×N   (validate → transform → updateOrCreate, isolated per slice)
        └─ FinalizeImportJob          (runs processor afterComplete(), settles final status)
```

Each chunk job processes `batch_size` rows, so jobs stay short, failures are isolated to a slice, and progress is accurate. Sessions end in `Completed`, `CompletedWithErrors`, `Failed`, or `Cancelled`.

## HTTP API

Set `IMPORT_EXPORT_ROUTES_ENABLED=true` (or `config('import-export.routes.enabled')`) to register the bundled API under the configured prefix (`api/import-export` by default) and middleware:

| Method | URI | Action |
|---|---|---|
| `GET` | `/sessions` | List import sessions |
| `POST` | `/sessions` | Create a session (upload file) |
| `GET` | `/sessions/{id}` | Show a session |
| `POST` | `/sessions/{id}/start` | Start processing |
| `DELETE` | `/sessions/{id}` | Cancel a session |
| `GET` | `/sessions/{id}/progress` | Poll progress |
| `GET` | `/sessions/{id}/failures` | Failure summary |
| `GET` | `/sessions/{id}/failures/export` | Download failed rows |
| `GET` | `/sessions/{id}/mappings` | List column mappings |
| `PUT` | `/sessions/{id}/mappings` | Update mappings |
| `GET` | `/sessions/{id}/mappings/suggestions` | Auto-match suggestions |
| `GET` | `/templates` | List mapping templates |
| `POST` | `/templates` | Create a template |
| `GET` `PUT` `DELETE` | `/templates/{id}` | Show / update / delete a template |

Prefer your own controllers? Leave routes disabled and call the `Actions` / `Services` directly.

## Exporting

```php
use Umutcangungormus\LaravelImportExport\Services\ModelExportService;

// streamed CSV/XLSX download
return app(ModelExportService::class)->export(App\Models\Product::class);
```

Export columns, accessors, and relations come from the model's `export_fields` / `export_with` config. Default format and chunk size are configurable under `export.*`.

## Localization

Translations ship under the `import-export::` namespace (English & Turkish) across `errors`, `export`, `fields`, `mapping`, `session`, `status`, and `template` groups. Publish with `--tag=import-export-lang` to customize, and override field labels/aliases via `import-export::fields.*`.

## Testing

```bash
composer test     # or: vendor/bin/pest
```

The suite runs on [Orchestra Testbench](https://github.com/orchestral/testbench) and covers the column matcher, file reader (CSV/XLSX/remote disk), failure handling, the full import flow, tenancy resolution, and HTTP route registration.

```
Tests:    36 passed (165 assertions)
```

## Frontend

A companion Vue 3 component library — drag-and-drop upload, column-mapping UI, progress, and a session manager — speaks this package's API out of the box:

➡️ **[@umut-can-gungormus/vue-import-export](https://github.com/UmutCanGungormus/vue-import-export)**

## Versioning

This package follows [Semantic Versioning](https://semver.org). See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE).

<div align="center">

Built with care by [Umut Can Gungormus](https://github.com/UmutCanGungormus).

</div>
