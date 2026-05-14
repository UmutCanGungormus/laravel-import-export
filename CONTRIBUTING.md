# Contributing

Thank you for considering a contribution. This document outlines how to get a development environment running, what's expected in a pull request, and the project's release/versioning policy.

## Development setup

```bash
git clone git@github.com:umutcangungormus/laravel-import-export.git
cd laravel-import-export
composer install
vendor/bin/pest
```

The package is tested against Orchestra Testbench with an in-memory SQLite database, so no external services are required.

## Running tests

```bash
vendor/bin/pest                          # full suite
vendor/bin/pest --filter='Unit'          # unit tests only
vendor/bin/pest --filter='Feature'       # feature tests only
vendor/bin/pest --filter='ImportFlow'    # one test file
```

The CI matrix runs PHP 8.3 and 8.4 against Laravel 11.\* and 12.\*. Please make sure your change works on at least one of those combinations locally before submitting a PR — CI will cover the rest.

## Code style

The package follows the Laravel default style. Run [Laravel Pint](https://laravel.com/docs/pint) before committing if you have it installed:

```bash
./vendor/bin/pint
```

The CI pipeline is currently strict only on tests; Pint will become a required check before v1.0.0.

## Branch naming

- `feature/<short-name>` for new features
- `fix/<short-name>` for bug fixes
- `chore/<short-name>` for docs, CI, refactors

Open the PR against `main`.

## Versioning

This project follows [Semantic Versioning](https://semver.org/). While the package is pre-1.0:

- `0.x.y → 0.x.(y+1)` for bug fixes
- `0.x.y → 0.(x+1).0` for new features OR breaking changes (anything goes in 0.x)
- Once we release `1.0.0`, breaking changes require a major bump.

Every release is preceded by a CHANGELOG entry under `## [Unreleased]` — moving it to a versioned heading is the last step of cutting a release.

## Submitting a pull request

1. Fork the repo and create a topic branch.
2. Write a failing test for the change (TDD strongly preferred).
3. Make the test pass.
4. Add an entry under `## [Unreleased]` in `CHANGELOG.md`.
5. Ensure `vendor/bin/pest` is green.
6. Open the PR using the template; reference any related issues.

## Code of conduct

Be kind, be constructive, assume good intent. Project maintainers reserve the right to remove comments / contributions that violate this principle.
