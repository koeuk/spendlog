<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Calendar labels for the date pickers, built server-side so they follow the app
 * locale — toLocaleDateString in the browser would follow the OS instead, and a
 * Khmer reader on an English machine would get English month names.
 *
 * Shared by the Budgets month picker and the Expenses date filter, so the two
 * cannot drift apart.
 */
class CalendarOptions
{
    /**
     * January–December, keyed '01'–'12' to match what the filters send.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function months(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month) => [
                'value' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                // Any year works — only the month name is read off it.
                'label' => CarbonImmutable::create(2000, $month, 1)->translatedFormat('F'),
            ])
            ->all();
    }
}
