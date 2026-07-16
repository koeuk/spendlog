<?php

namespace App\Support;

use App\Enums\Locale;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Filtering and sorting for translatable JSON columns.
 *
 * A translatable column holds {"en": "...", "km": "..."}, so the plain
 * AllowedFilter::partial() / allowedSorts() both operate on the raw JSON text
 * rather than on a value anyone typed:
 *
 *   - partial('item') compiles to `item LIKE '%en%'`, and every row contains the
 *     literal key "en" — so searching "en" matched all 1101 rows.
 *   - sorting on the column orders by the JSON string, which is only ever the
 *     English value by accident of key order.
 *
 * These build the same filters against JSON paths instead. Shared by the web and
 * API controllers so the two can never disagree about what a search means.
 */
class TranslatableQuery
{
    /**
     * Partial match across every locale, so a Khmer search finds Khmer values
     * and an English one finds English — without the user picking a language.
     */
    public static function filter(string $field, ?string $name = null): AllowedFilter
    {
        return AllowedFilter::callback(
            $name ?? $field,
            function (Builder $query, mixed $value) use ($field) {
                // Grouped: without the closure the ORs would escape and defeat
                // the owner scope applied later in the controller.
                $query->where(function (Builder $inner) use ($field, $value) {
                    foreach (Locale::cases() as $locale) {
                        $inner->orWhere("{$field}->{$locale->value}", 'like', '%'.$value.'%');
                    }
                });
            },
        );
    }

    /** Order by the value the reader is actually seeing, not the raw JSON. */
    public static function sort(string $field, ?string $name = null): AllowedSort
    {
        return AllowedSort::callback(
            $name ?? $field,
            function (Builder $query, bool $descending) use ($field) {
                $query->orderBy(
                    $field.'->'.app()->getLocale(),
                    $descending ? 'desc' : 'asc',
                );
            },
        );
    }
}
