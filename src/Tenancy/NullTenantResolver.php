<?php

namespace Umutcangungormus\LaravelImportExport\Tenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * Default tenant resolver: returns `null` for the current tenant id and
 * leaves queries untouched. Perfect for single-tenant apps and the test
 * suite. Replace via `config('import-export.tenancy.resolver')` in your
 * own multi-tenant projects.
 */
final class NullTenantResolver implements TenantResolverContract
{
    public function currentTenantId(): int|string|null
    {
        return null;
    }

    public function scopeQuery(Builder $query): Builder
    {
        return $query;
    }
}
