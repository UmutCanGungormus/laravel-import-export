<?php

namespace Umutcangungormus\LaravelImportExport\Tenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adapter point for multi-tenant host apps.
 *
 * Bind your implementation in the service container (default binding is
 * driven by `config('import-export.tenancy.resolver')` and defaults to
 * {@see NullTenantResolver}).
 */
interface TenantResolverContract
{
    /**
     * Resolve the currently-active tenant identifier (e.g. company id,
     * workspace id, organisation slug, …). Returning `null` indicates the
     * host is single-tenant.
     */
    public function currentTenantId(): int|string|null;

    /**
     * Optionally scope an Eloquent query to the current tenant. Hosts that
     * use global scopes (e.g. spatie/laravel-multitenancy, stancl/tenancy)
     * can simply return the unmodified builder here.
     */
    public function scopeQuery(Builder $query): Builder;
}
