# Changelog

All notable changes to `umutcangungormus/laravel-import-export` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `HasImportExport::formatExportValue` and `ModelExportService::formatValue` now emit the localized "Yes" / "No" cell value for boolean export columns instead of the literal lang key. The lang files have been split into per-group files (`status.php`, `session.php`, `mapping.php`, `template.php`, `export.php`, `errors.php`, `fields.php`) under `lang/{en,tr}/` so that `__('import-export::group.key')` resolves through Laravel's standard namespace.key path.
- `FileReaderService` now works on disks whose driver does not support `path()` (S3, GCS, Azure, in-memory). When `path()` throws, the file is spooled to a tempfile via `readStream()` and the tempfile is unlinked deterministically in a `finally` block after the read closure returns.
- `FileReaderService::xmlReaderFromStream` no longer leaks tempfiles via `register_shutdown_function`. Each XLSX read now cleans up its sidecar tempfiles (shared strings, styles, workbook, rels, worksheet) inline through a `finally` block tied to the iteration's lifetime, and copies the worksheet XML via `stream_copy_to_stream` instead of slurping it into RAM. A new XLSX integration test covers headers + data rows and asserts no `xlsx_*` tempfiles linger in `sys_get_temp_dir()` after reading.
- `ImportTemplateController::store` now forwards `TenantResolverContract::currentTenantId()` to `MappingTemplateService::create()` instead of hardcoding `null`, so templates created via the HTTP layer carry the host's tenant id — matching the headless API and `ImportSessionController`.

### Changed

- `FailureHandlerContract` now declares `summary(ImportSession $session): array` as part of the interface. The leaky `method_exists` probe in `ImportExportService::getFailuresSummary` is gone; any custom failure handler bound to the contract must now implement `summary()` (the bundled `FailureHandlerService` already did).

### Removed

- Removed `prepareForImport()` and `afterImport()` from the `Importable` contract and `HasImportExport` trait. Row-level lifecycle hooks live exclusively on `ImportProcessorInterface` (the documented public extension point) — the trait methods were dead code that `ProcessImportJob` never invoked, and shipping two overlapping integration points was a confusing extension surface.

- Dropped `maatwebsite/excel` from `require`: the package implements its own streaming CSV (`fgetcsv`) and XLSX (`XMLReader` + `ZipArchive`) readers, so consumers no longer pull `phpoffice/phpspreadsheet` transitively.
- Dropped `spatie/laravel-data` from `require`: every shipped DTO is a bare `readonly` constructor-promoted class, none extend `Spatie\LaravelData\Data`.

### Added

- Initial extraction of the framework-grade Import/Export subsystem as a standalone Laravel package.
- Contracts: `Importable`, `Exportable`, `ColumnMatcherContract`, `FailureHandlerContract`, `ImportProcessorInterface`.
- Tenancy abstraction: `TenantResolverContract` + `NullTenantResolver` (default binding).
- Eloquent models with config-driven table names: `ImportSession`, `ImportColumnMapping`, `ImportMappingTemplate`, `ImportFailure`.
- Enums: `ImportStatus`, `MatchMethod`.
- Services: `ImportExportService`, `ColumnMatcherService`, `FileReaderService` (streaming CSV/XLSX reader with full Excel date-system handling), `ModelExportService`, `FailureHandlerService`, `MappingTemplateService`, `ImportMappingTemplateService`.
- Pipelines: `DetectFileHeaders`, `AutoMatchColumns`, `ValidateRequiredMappings`, `ApplyDefaultTemplate`.
- Actions: `InitializeImportAction`, `StartImportAction`, `UpdateMappingAction`, `CreateTemplateAction`, `SaveTemplateAction`, `ApplyTemplateAction`.
- Queued worker: `ProcessImportJob` (Illuminate queue contracts only, Horizon-compatible).
- DTOs (readonly): `InitializeImportData`, `UpdateMappingData`, `ApplyTemplateData`, `CreateTemplateData`, `SaveTemplateData`, `UpdateTemplateData`.
- `HasImportExport` trait for config-driven Importable/Exportable wiring.
- Publishable resources: `config/import-export.php`, four migrations, English + Turkish lang files.
- Optional HTTP layer behind `routes.enabled` flag: three controllers, four resources, six form requests, a route file.
- Pest test suite (Orchestra Testbench) covering unit + feature flows: full pipeline happy path, failure persistence, tenancy injection, route enable/disable, gate-name discovery.
- GitHub Actions CI: 2×2 matrix on PHP 8.3 / 8.4 × Laravel 11 / 12.
- OSS hygiene: README, LICENSE (MIT), CHANGELOG, CONTRIBUTING, issue + pull-request templates.
