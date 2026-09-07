<?php

namespace Umutcangungormus\LaravelImportExport\Enums;

/**
 * How several source columns pointed at one target field become a single value.
 *
 * A target is only ever fed by more than one column when its field config marks
 * it `multi` *and* the user picked a strategy, so the historic one-column-wins
 * behaviour is what a mapping without a strategy still gets.
 */
enum MultiColumnStrategy: string
{
    /** Join the cells with a single space, in file column order. */
    case Merge = 'merge';

    /** Store a `{"column name": "cell"}` object, in file column order. */
    case Json = 'json';

    /**
     * Folds the cells of one row into the single value the target receives.
     *
     * Blank cells drop out of both shapes: a column the row left empty should
     * not add a stray separator or a `""` entry. A row that leaves every column
     * empty yields null, which is what an unmapped target would have given.
     *
     * @param  array<string, mixed>  $bySource  Source column => cell, in file column order
     * @return ?string The combined value, or null when every cell was blank
     */
    public function combine(array $bySource): ?string
    {
        $filled = [];

        foreach ($bySource as $sourceColumn => $value) {
            if ($value === null || is_array($value)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text !== '') {
                $filled[$sourceColumn] = $text;
            }
        }

        if ($filled === []) {
            return null;
        }

        return match ($this) {
            self::Merge => implode(' ', array_values($filled)),
            self::Json => (string) json_encode($filled, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * Resolves a stored strategy name, tolerating null and unknown values.
     *
     * @param  mixed  $value  The name read back off a mapping's transformation rules
     * @return ?self The strategy, or null when the mapping declares none
     */
    public static function tryFromValue(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }
}
