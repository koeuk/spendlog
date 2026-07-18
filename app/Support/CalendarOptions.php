<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
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

    /**
     * Years the user could plausibly want, drawn from their own data rather than
     * an arbitrary range: offering 1990 when the first expense is from 2024 is
     * just a longer list to scroll.
     *
     * $viewing is always included — someone can step to next year with the
     * Budgets page arrows, and the dropdown has to be able to show where they
     * are even when no data lives there yet.
     *
     * @return array<int, int>
     */
    public static function years(User $user, CarbonImmutable $viewing): array
    {
        $earliest = min(array_filter([
            Expense::where('user_id', $user->id)->min('spent_on'),
            Budget::where('user_id', $user->id)->min('month'),
        ]) ?: [CarbonImmutable::now()->toDateString()]);

        $first = (int) CarbonImmutable::parse($earliest)->year;
        $last = CarbonImmutable::now()->year;

        return range(
            min($first, $viewing->year),
            max($last, $viewing->year),
        );
    }

    /**
     * A 'YYYY-MM' string as the first day of that month, falling back to the
     * current month for anything malformed.
     *
     * A query string, not a form — a junk value navigates somewhere sensible
     * rather than 500ing.
     *
     * The shape check is not enough on its own: '2026-13' matches it, and Carbon
     * does not throw on the overflow — it rolls forward to 2027-01 and hands back
     * a date nobody asked for. So the result has to format back to what came in.
     */
    public static function resolveMonth(?string $month): CarbonImmutable
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                $parsed = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

                if ($parsed->format('Y-m') === $month) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
