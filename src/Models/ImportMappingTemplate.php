<?php

namespace Umutcangungormus\LaravelImportExport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|string|null $tenant_id
 * @property string $importable_type
 * @property string $template_name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_company_wide
 * @property array $template_data
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @mixin \Eloquent
 */
class ImportMappingTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'importable_type',
        'template_name',
        'description',
        'is_default',
        'is_company_wide',
        'template_data',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'template_data' => 'array',
        'is_default' => 'boolean',
        'is_company_wide' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('import-export.tables.mapping_templates', 'import_mapping_templates');
    }

    public function markAsUsed(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function setAsDefault(): void
    {
        // Remove default flag from all other templates for same user + model
        static::query()
            ->where('user_id', $this->user_id)
            ->where('importable_type', $this->importable_type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
