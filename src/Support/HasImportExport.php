<?php

namespace Umutcangungormus\LaravelImportExport\Support;


/**
 * HasImportExport
 *
 * Add this trait to any Eloquent model to opt into the ImportExport system.
 * Field definitions live in config/import-export.php under the 'models' key —
 * no getImportableFields() / getExportableFields() needed in the model itself.
 *
 * Minimal model setup:
 *
 *   class Product extends Model implements Importable, Exportable
 *   {
 *       use HasImportExport;
 *   }
 *
 * Everything else (fields, aliases, validation, unique_by, export_with…)
 * is defined in `config('import-export.models')` under the model's FQCN.
 *
 * Field labels and aliases are pulled from the namespaced lang file:
 *   `trans('import-export::fields.<key>')`
 */
trait HasImportExport
{
    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Resolve a lang key from `import-export::fields.<key>` and return
     * `['label', 'aliases']`. Falls back gracefully when the key does not exist.
     */
    private static function resolveFieldLang(string $langKey): array
    {
        // e.g. "product.sku" → import-export::fields.product.sku
        $path = 'import-export::fields.'.$langKey;

        // label: active locale
        $cur = trans($path);

        // aliases: merge EN + TR so both column-name variants are recognised
        $en = trans($path, [], 'en');
        $tr = trans($path, [], 'tr');

        // If the translation returns the key itself (missing), fall back to ''
        $label = is_array($cur) ? ($cur['label'] ?? $langKey) : (is_string($cur) ? $cur : $langKey);
        $aliasEn = is_array($en) ? ($en['aliases'] ?? []) : [];
        $aliasTr = is_array($tr) ? ($tr['aliases'] ?? []) : [];

        return [
            'label' => $label,
            'aliases' => array_values(array_unique(array_merge($aliasEn, $aliasTr))),
        ];
    }

    private static function modelConfig(): array
    {
        return config('import-export.models.'.static::class, []);
    }

    // ── Importable ────────────────────────────────────────────────────────
    //
    // Row-level lifecycle hooks (prepare / after) live exclusively on
    // ImportProcessorInterface, the documented public extension point —
    // see README "Extending the Importer". ProcessImportJob always resolves
    // the processor from config('import-export.models.<class>.processor')
    // and never calls back into the model's trait.

    public static function getImportableFields(): array
    {
        $cfg = static::modelConfig();
        $result = [];

        foreach ($cfg['fields'] ?? [] as $field => $def) {
            $lang = isset($def['lang'])
                ? static::resolveFieldLang($def['lang'])
                : ['label' => $field, 'aliases' => []];

            $result[$field] = array_merge(
                [
                    'label' => $lang['label'],
                    'aliases' => $lang['aliases'],
                ],
                $def,
            );

            // Remove internal-only key from the exposed array
            unset($result[$field]['lang']);
        }

        return $result;
    }

    public static function getImportUniqueBy(): ?array
    {
        return static::modelConfig()['unique_by'] ?? null;
    }

    // ── Exportable ────────────────────────────────────────────────────────

    public static function getExportableFields(): array
    {
        $cfg = static::modelConfig();
        $result = [];

        foreach ($cfg['export_fields'] ?? [] as $field => $def) {
            $lang = isset($def['lang'])
                ? static::resolveFieldLang($def['lang'])
                : ['label' => $field, 'aliases' => []];

            $result[$field] = array_merge(['label' => $lang['label']], $def);
            unset($result[$field]['lang']);
        }

        return $result;
    }

    public static function modifyExportQuery($query)
    {
        $relations = static::modelConfig()['export_with'] ?? [];

        return empty($relations) ? $query : $query->with($relations);
    }

    // ── Export formatting ─────────────────────────────────────────────────

    public static function transformForExport($model): array
    {
        $data = [];

        foreach (static::getExportableFields() as $field => $cfg) {
            $accessor = $cfg['accessor'] ?? $field;

            $value = is_callable($accessor)
                ? call_user_func($accessor, $model)
                : data_get($model, $accessor);

            if (isset($cfg['format']) && ! is_null($value)) {
                $value = static::formatExportValue($value, $cfg['format']);
            }

            $data[$field] = $value;
        }

        return $data;
    }

    protected static function formatExportValue($value, string $format): mixed
    {
        return match ($format) {
            'date' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d') : $value,
            'datetime' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d H:i:s') : $value,
            'time' => $value instanceof \Carbon\Carbon ? $value->format('H:i:s') : $value,
            'boolean' => $value ? __('import-export::export.yes') : __('import-export::export.no'),
            'number' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'currency' => is_numeric($value) ? number_format((float) $value, 2, '.', ',') : $value,
            default => $value,
        };
    }
}
