# Laravel Import / Export

[![tests](https://github.com/umutcangungormus/laravel-import-export/actions/workflows/tests.yml/badge.svg)](https://github.com/umutcangungormus/laravel-import-export/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/umutcangungormus/laravel-import-export.svg)](https://packagist.org/packages/umutcangungormus/laravel-import-export)
[![License](https://img.shields.io/packagist/l/umutcangungormus/laravel-import-export.svg)](LICENSE)

Framework-grade, tenant-agnostic CSV / XLSX import + export pipeline for Laravel 11 and 12.

The package ships:

- A streaming file reader (CSV via `fgetcsv`, XLSX via `XMLReader` + `ZipArchive::getStream` — no full-file loads, full Excel date-system handling).
- A four-stage import pipeline (detect headers → auto-match columns → apply default template → validate required mappings) built on Laravel's `Pipeline` facade.
- A pluggable column-matcher with exact / label / alias / fuzzy strategies and confidence scoring.
- A pluggable failure-recorder that streams CSVs of per-row errors back to the user.
- A mapping-template system with default-template auto-apply.
- A queued worker (`ProcessImportJob`) that delegates row-level transforms to host-supplied `ImportProcessorInterface` implementations — the package itself ships **no** model-specific processors.
- An optional HTTP layer (controllers, requests, resources, route file) behind a single config flag.
- A `TenantResolverContract` so the package works out-of-the-box in single-tenant apps and integrates cleanly with `stancl/tenancy`, `spatie/laravel-multitenancy`, or your own resolver.
- Publishable migrations, config, and English / Turkish lang files.

## Installation

```bash
composer require umutcangungormus/laravel-import-export
```

The service provider is auto-discovered. Publish the resources you want to customise:

```bash
php artisan vendor:publish --tag=import-export-config
php artisan vendor:publish --tag=import-export-migrations
php artisan vendor:publish --tag=import-export-lang

php artisan migrate
```

## Usage

### 1. Wire a model into the system

Mark any Eloquent model as `Importable` / `Exportable` and `use` the trait. The trait reads everything from `config/import-export.php`, so the model itself stays minimal.

```php
use Illuminate\Database\Eloquent\Model;
use Umutcangungormus\LaravelImportExport\Contracts\Exportable;
use Umutcangungormus\LaravelImportExport\Contracts\Importable;
use Umutcangungormus\LaravelImportExport\Support\HasImportExport;

class Product extends Model implements Importable, Exportable
{
    use HasImportExport;
}
```

### 2. Register the model + a processor in config

```php
// config/import-export.php

return [
    'models' => [
        App\Models\Product::class => [
            'processor'   => App\Imports\Processors\ProductProcessor::class,
            'unique_by'   => ['sku'],
            'export_with' => [],
            'fields' => [
                'sku'  => ['required' => true, 'type' => 'string',  'validation' => ['required', 'string', 'max:64']],
                'name' => ['required' => true, 'type' => 'string',  'validation' => ['required', 'string', 'max:255']],
                'price'=> ['required' => false,'type' => 'decimal', 'validation' => ['nullable', 'numeric']],
            ],
            'export_fields' => [
                'id'   => ['accessor' => 'id'],
                'sku'  => ['accessor' => 'sku'],
                'name' => ['accessor' => 'name'],
                'price'=> ['accessor' => 'price', 'format' => 'currency'],
            ],
        ],
    ],
];
```

### 3. Implement the processor in your host app

```php
use Umutcangungormus\LaravelImportExport\Contracts\ImportProcessorInterface;
use Umutcangungormus\LaravelImportExport\Models\ImportSession;

class ProductProcessor implements ImportProcessorInterface
{
    public function prepare(ImportSession $session, array $data): array
    {
        // Resolve foreign keys, attach tenant id, normalize values, …
        $data['tenant_id'] = $session->tenant_id;
        return $data;
    }

    public function after(object $model, array $data): void
    {
        // e.g. enqueue a notification, broadcast, index, etc.
    }
}
```

### 4. Initialise + start an import

```php
use Umutcangungormus\LaravelImportExport\Data\InitializeImportData;
use Umutcangungormus\LaravelImportExport\Services\ImportExportService;

$service = app(ImportExportService::class);

$session = $service->initialize(
    new InitializeImportData(
        model_class: App\Models\Product::class,
        file_path:   'imports/products.csv',
        file_name:   'products.csv',
        file_disk:   'local',
        tenant_id:   null, // auto-resolved from TenantResolverContract
        header_row:  1,
        chunk_size:  500,
    ),
    userId: auth()->id(),
);

// User reviews mappings here (via your UI, or via the bundled HTTP layer)…

$service->start($session->id); // queues ProcessImportJob
```

### 5. (Optional) Turn on the bundled HTTP layer

```php
// config/import-export.php
'routes' => [
    'enabled'    => true,
    'prefix'     => 'api/import-export',
    'middleware' => ['api', 'auth:sanctum'],
],
```

That registers `GET /sessions`, `POST /sessions`, `GET /sessions/{id}`, `POST /sessions/{id}/start`, `PUT /sessions/{id}/mappings`, etc. — see `routes/api.php` for the full list.

## Configuration

Every option is documented inline in the published `config/import-export.php`. Highlights:

| Key | Type | Purpose |
|---|---|---|
| `models` | `array` | Registry mapping model FQCN → field schema + processor. |
| `tables.*` | `string` | Override the four table names. |
| `disk` / `storage_path` | `string` | Where uploaded source files land. |
| `chunk_size` | `int` | Default chunk size for the row reader. |
| `column_matching.auto_confirm_threshold` | `float` | Score ≥ this is auto-confirmed (default 0.8). |
| `column_matching.suggestion_threshold` | `float` | Score ≥ this becomes a UI suggestion (default 0.3). |
| `templates.auto_apply_default` | `bool` | Auto-apply the user's default template after auto-match. |
| `queue.connection` / `queue.queue` | `string\|null` | Queue routing for `ProcessImportJob`. |
| `tenancy.resolver` | `class-string<TenantResolverContract>` | Override the resolver. |
| `routes.enabled` | `bool` | Master switch for the bundled HTTP layer. |
| `gates.*` | `string` | Ability names the host binds via `Gate::define`. |

## Extending

### Custom import processor

Implement `Umutcangungormus\LaravelImportExport\Contracts\ImportProcessorInterface` and register the class under `config('import-export.models.<Model>.processor')`. The package looks the processor up via the container, so constructor injection works.

### Custom column matcher

```php
$this->app->bind(
    \Umutcangungormus\LaravelImportExport\Contracts\ColumnMatcherContract::class,
    YourMatcher::class,
);
```

### Custom failure handler

Bind your own implementation of `FailureHandlerContract` to ship rejected rows to S3, OpenTelemetry, Sentry, etc.

### Custom tenant resolver

See the next section.

## Tenancy

The package is tenant-agnostic. It exposes a single contract:

```php
namespace Umutcangungormus\LaravelImportExport\Tenancy;

interface TenantResolverContract
{
    public function currentTenantId(): int|string|null;
    public function scopeQuery(Builder $query): Builder;
}
```

Out of the box the package binds `NullTenantResolver` — `currentTenantId()` returns `null`, queries are unchanged. Multi-tenant apps replace this:

```php
// config/import-export.php
'tenancy' => [
    'resolver' => App\Import\ResolveCompanyFromAuthToken::class,
],
```

Every `ImportSession` and `ImportMappingTemplate` will then carry the resolver's id in its `tenant_id` column.

## Authorization

The package **does not** define any gates. Instead it names the abilities in config and assumes the host has bound them:

```php
// config/import-export.php
'gates' => [
    'session_create' => 'import-export.session.create',
    'session_view'   => 'import-export.session.view',
    // …
],

// app/Providers/AuthServiceProvider.php
Gate::define('import-export.session.create', fn ($user) => $user->can('create:import'));
```

This keeps the package compatible with `spatie/laravel-permission`, `silber/bouncer`, raw `Gate::define`, or no auth at all.

## Testing

```bash
composer install
vendor/bin/pest
```

The CI matrix runs PHP 8.3 / 8.4 × Laravel 11 / 12 on every push and pull request.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
