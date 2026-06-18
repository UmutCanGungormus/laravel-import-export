<?php

use Umutcangungormus\LaravelImportExport\Tenancy\NullTenantResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | File Storage
    |--------------------------------------------------------------------------
    |
    | The Laravel disk used to store uploaded import source files, and the
    | base path within that disk. Override per environment via .env.
    */
    'disk' => env('IMPORT_EXPORT_DISK', 'local'),
    'storage_path' => env('IMPORT_EXPORT_STORAGE_PATH', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Override these to use custom table names (helpful when integrating
    | into an existing schema, namespacing per tenant, etc.).
    */
    'tables' => [
        'sessions' => 'import_sessions',
        'column_mappings' => 'import_column_mappings',
        'mapping_templates' => 'import_mapping_templates',
        'failures' => 'import_failures',
        // Name of the host app's users table. The migrations only reference
        // this table when the host opts into the bundled FK constraints.
        'users' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign Keys
    |--------------------------------------------------------------------------
    |
    | Toggle bundled FK constraints. Disable when your `users` table lives
    | on a different connection or when you manage referential integrity
    | externally (sharding, polyglot persistence, etc.).
    */
    'foreign_keys' => [
        'users' => env('IMPORT_EXPORT_USERS_FK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing
    |--------------------------------------------------------------------------
    */
    'chunk_size' => env('IMPORT_EXPORT_CHUNK_SIZE', 1000),

    // Rows per queued ProcessImportChunkJob. ProcessImportJob (the planner)
    // splits the file into a Bus batch — one chunk job per this many data
    // rows — so each job stays short (survives worker timeouts), row failures
    // are isolated to a slice, and progress is real. FinalizeImportJob runs
    // any post-batch pass once the batch completes.
    'batch_size' => (int) env('IMPORT_EXPORT_BATCH_SIZE', 500),

    'job_timeout' => env('IMPORT_EXPORT_JOB_TIMEOUT', 600),
    'job_tries' => env('IMPORT_EXPORT_JOB_TRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Connection + queue name used by ProcessImportJob. `null` falls back to
    | the application defaults. Horizon-compatible without any direct
    | Horizon dependency.
    |
    | For large imports, point `connection` at a dedicated queue connection
    | whose `retry_after` (see config/queue.php) is larger than `job_timeout`,
    | so a still-running import is never silently re-dispatched onto a second
    | worker. Keep `job_tries` at 1 for that connection: a half-finished bulk
    | import must not auto-retry, which would double-process rows.
    */
    'queue' => [
        'connection' => env('IMPORT_EXPORT_QUEUE_CONNECTION'),
        'queue' => env('IMPORT_EXPORT_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Matching
    |--------------------------------------------------------------------------
    */
    'column_matching' => [
        // >= this score → auto-confirmed
        'auto_confirm_threshold' => 0.8,
        // >= this score → shown as a suggestion
        'suggestion_threshold' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Template System
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'enabled' => true,
        'auto_apply_default' => true,
        'max_per_user' => 50,
        'validate_before_apply' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */
    'export' => [
        'default_format' => 'csv', // 'csv' | 'xlsx'
        'chunk_size' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | The package itself is tenant-agnostic. Bind a custom resolver class
    | here to inject your tenant id (company, workspace, organisation, …)
    | into every ImportSession.
    |
    | The default `NullTenantResolver` returns null — perfect for
    | single-tenant apps.
    */
    'tenancy' => [
        'resolver' => NullTenantResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Routes
    |--------------------------------------------------------------------------
    |
    | The package ships a controller+request+resource set you can opt into.
    | Set `enabled => true` to register the API routes, or wire your own
    | controllers and skip this entirely.
    */
    'routes' => [
        'enabled' => env('IMPORT_EXPORT_ROUTES_ENABLED', false),
        'prefix' => env('IMPORT_EXPORT_ROUTES_PREFIX', 'api/import-export'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate Abilities
    |--------------------------------------------------------------------------
    |
    | The package only names the abilities here — your host app binds the
    | actual `Gate::define()` rules (or uses spatie/laravel-permission,
    | Bouncer, etc.). This keeps the package agnostic of any specific
    | authorization stack.
    */
    'gates' => [
        'session_create' => 'import-export.session.create',
        'session_view' => 'import-export.session.view',
        'session_update' => 'import-export.session.update',
        'session_cancel' => 'import-export.session.cancel',
        'template_create' => 'import-export.template.create',
        'template_view' => 'import-export.template.view',
        'template_update' => 'import-export.template.update',
        'template_delete' => 'import-export.template.delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Registry (filled by host)
    |--------------------------------------------------------------------------
    |
    | Map fully-qualified Eloquent model class names to their importable
    | field schemas. The package ships an empty registry — host apps
    | publish this file and fill it in. See README "Configuration" for the
    | full schema. Example below is documentation only and intentionally
    | commented out.
    |
    | 'models' => [
    |     App\Models\Product::class => [
    |         'unique_by'   => ['sku'],
    |         'export_with' => [],
    |         'processor'   => App\Imports\Processors\ProductProcessor::class,
    |         'fields' => [
    |             'sku'  => ['required' => true, 'type' => 'string',  'validation' => ['required', 'string', 'max:64']],
    |             'name' => ['required' => true, 'type' => 'string',  'validation' => ['required', 'string', 'max:255']],
    |         ],
    |         'export_fields' => [
    |             'id'   => ['accessor' => 'id'],
    |             'sku'  => ['accessor' => 'sku'],
    |             'name' => ['accessor' => 'name'],
    |         ],
    |     ],
    | ],
    */
    'models' => [
        // Host apps register their models here.
    ],
];
