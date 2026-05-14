# Changelog

All notable changes to `umutcangungormus/laravel-import-export` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `HasImportExport::formatExportValue` and `ModelExportService::formatValue` now emit the localized "Yes" / "No" cell value for boolean export columns instead of the literal lang key. The lang files have been split into per-group files (`status.php`, `session.php`, `mapping.php`, `template.php`, `export.php`, `errors.php`, `fields.php`) under `lang/{en,tr}/` so that `__('import-export::group.key')` resolves through Laravel's standard namespace.key path.

### Removed

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
