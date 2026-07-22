<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A fixed spending history for one user: 2023 to today, summing to exactly
 * TOTAL_USD.
 *
 * ExpenseSeeder fills every account with an arbitrary total; this one exists
 * for the opposite case — a single account whose lifetime figure has to land
 * on a known number, so every screen that sums it (dashboard, reports, the
 * budgets page) can be checked against $4,500 by eye.
 *
 * The shape borrows ExpenseSeeder's approach — weighted categories, per-band
 * prices, a seeded RNG, bulk insert in chunks — but the prices are then scaled
 * so the whole range sums to TOTAL_USD to the cent, with the final row
 * absorbing the rounding drift.
 *
 *     php artisan db:seed --class=UserSpendingSeeder
 *
 * Re-runnable: the target user's expenses are wiped first, and the RNG seed is
 * fixed, so every run rebuilds the same books.
 */
class UserSpendingSeeder extends Seeder
{
    /** Who gets the history. */
    private const EMAIL = 'koeukkos@gmail.com';

    /** What the whole range must sum to, in stored USD. */
    private const TOTAL_USD = 4500.00;

    /** Inclusive range of years to fill; the last stops at today. */
    private const FIRST_YEAR = 2023;

    private const LAST_YEAR = 2026;

    /** Rows across the whole range — roughly a purchase a day. With the five
     *  fixed one-offs on top, the account holds 1,234 expenses. */
    private const TOTAL_ROWS = 1229;

    /** Rows written per INSERT. */
    private const CHUNK = 500;

    /**
     * The one-off purchases, exactly as they happened: [category, item, price,
     * date]. These are real fixed figures, so they sit outside the everyday
     * profile and are never scaled — the $4,500 everyday total rides on top.
     *
     * @return array<int, array{0: string, 1: string, 2: float, 3: string}>
     */
    private function fixedPurchases(): array
    {
        return [
            ['Shopping', 'Computer', 890.00, '2023-06-15'],
            ['Transport', 'Motorbike', 1850.00, '2025-01-10'],
            ['Shopping', 'Computer', 550.00, '2025-06-10'],
            ['Education', 'School fee', 720.00, '2025-09-01'],
            ['Education', 'School fee', 900.00, '2026-01-05'],
        ];
    }

    /**
     * [category, weight, min price, max price, items].
     *
     * No Rent here, unlike ExpenseSeeder's profile: $4,500 over four years is
     * about $94 a month, and a monthly rent row would eat the entire figure.
     * This is everyday money — food, coffee, transport — with travel as the
     * occasional spike.
     *
     * @return array<int, array{0: string, 1: int, 2: float, 3: float, 4: array<int, string>}>
     */
    private function profile(): array
    {
        return [
            ['Food', 22, 2.50, 12.00, ['Lunch', 'Dinner', 'Street food', 'Noodles']],
            ['Coffee', 20, 0.50, 4.50, ['Morning coffee', 'Iced coffee', 'Iced tea']],
            ['Groceries', 12, 5.00, 30.00, ['Weekly shop', 'Market run', 'Top-up shop']],
            ['Transport', 12, 1.00, 15.00, ['Grab', 'Fuel', 'Tuk-tuk', 'Parking']],
            ['Snacks', 8, 0.75, 5.00, ['Snack run', 'Bakery', 'Fruit shake']],
            ['Beer', 6, 4.00, 25.00, ['Round with the team', 'Weekend', 'Night out']],
            ['Phone', 5, 1.00, 10.00, ['Top-up', 'Data pack']],
            ['Entertainment', 5, 3.00, 15.00, ['Cinema', 'Streaming', 'Concert']],
            ['Health', 4, 3.00, 20.00, ['Pharmacy', 'Check-up']],
            ['Gifts', 3, 5.00, 30.00, ['Birthday', 'Wedding']],
            ['Travel', 3, 20.00, 90.00, ['Weekend trip', 'Bus ticket', 'Hotel']],
        ];
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('UserSpendingSeeder refuses to run in production.');

            return;
        }

        $user = User::where('email', self::EMAIL)->first();

        if (! $user) {
            $this->command?->warn(sprintf('UserSpendingSeeder: no user with email %s.', self::EMAIL));

            return;
        }

        $categoryIds = $this->categoryIds();

        if ($categoryIds->isEmpty()) {
            $this->command?->warn('UserSpendingSeeder: no categories — run CategorySeeder first.');

            return;
        }

        // Wiped rather than added to, so the lifetime figure is TOTAL_USD and
        // not TOTAL_USD plus whatever was there before.
        Expense::where('user_id', $user->id)->delete();

        $rows = $this->buildRows($user, $categoryIds);
        $this->scaleToTotal($rows);

        // After scaling, so the everyday spend still sums to TOTAL_USD and
        // these keep their real prices on top of it.
        foreach ($this->fixedPurchases() as [$name, $item, $price, $date]) {
            $categoryId = $categoryIds[$name] ?? null;

            if (! $categoryId) {
                $this->command?->warn(sprintf('UserSpendingSeeder: no "%s" category — skipped %s.', $name, $item));

                continue;
            }

            $rows[] = $this->row($user->id, $categoryId, $item, $price, CarbonImmutable::parse($date));
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('expenses')->insert($chunk);
        }

        $this->command?->info(sprintf(
            'UserSpendingSeeder: %d expenses for %s, %d–today, totalling $%s.',
            count($rows),
            self::EMAIL,
            self::FIRST_YEAR,
            number_format(array_sum(array_column($rows, 'price')), 2),
        ));
    }

    /**
     * Every row for the whole range, prices still unscaled.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $categoryIds
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(User $user, $categoryIds): array
    {
        // Fixed, not time-derived: re-running must rebuild the same books.
        mt_srand($user->id * 7919);

        $now = CarbonImmutable::now()->startOfDay();
        $weighted = $this->weightedCategories();
        $rows = [];

        foreach ($this->allocation($now) as $year => $count) {
            $start = CarbonImmutable::create($year, 1, 1);
            $end = $start->endOfYear()->startOfDay();

            // The current year stops at today — ExpenseRequest rejects future
            // dates, so the app itself would refuse anything later.
            if ($end->isAfter($now)) {
                $end = $now;
            }

            $span = max(1, (int) $start->diffInDays($end));

            for ($i = 0; $i < $count; $i++) {
                [$name, , $min, $max, $items] = $weighted[mt_rand(0, count($weighted) - 1)];

                $categoryId = $categoryIds[$name] ?? null;

                if (! $categoryId) {
                    continue;
                }

                $rows[] = $this->row(
                    $user->id,
                    $categoryId,
                    $items[mt_rand(0, count($items) - 1)],
                    round(mt_rand((int) ($min * 100), (int) ($max * 100)) / 100, 2),
                    $start->addDays(mt_rand(0, $span)),
                );
            }
        }

        return $rows;
    }

    /**
     * Scale every price so the set sums to exactly TOTAL_USD.
     *
     * Rounding each scaled price to a cent leaves the sum a few cents off, so
     * the last row takes the difference — one row a few cents odd beats a
     * total that misses the target it exists to hit.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function scaleToTotal(array &$rows): void
    {
        $sum = array_sum(array_column($rows, 'price'));

        if ($sum <= 0) {
            return;
        }

        $factor = self::TOTAL_USD / $sum;
        $running = 0.0;
        $last = count($rows) - 1;

        foreach ($rows as $i => &$row) {
            $row['price'] = $i === $last
                ? round(self::TOTAL_USD - $running, 2)
                : round($row['price'] * $factor, 2);

            $running = round($running + $row['price'], 2);
        }
    }

    /**
     * TOTAL_ROWS split across the years in proportion to elapsed days, by
     * largest remainder — same reasoning as ExpenseSeeder::allocation.
     *
     * @return array<int, int> year => row count
     */
    private function allocation(CarbonImmutable $now): array
    {
        $days = [];

        foreach (range(self::FIRST_YEAR, self::LAST_YEAR) as $year) {
            $start = CarbonImmutable::create($year, 1, 1);

            if ($start->isAfter($now)) {
                $days[$year] = 0;

                continue;
            }

            $end = $start->endOfYear()->startOfDay();
            $days[$year] = (int) $start->diffInDays($end->isAfter($now) ? $now : $end) + 1;
        }

        $totalDays = array_sum($days);

        if ($totalDays === 0) {
            return [];
        }

        $counts = [];
        $remainders = [];

        foreach ($days as $year => $dayCount) {
            $exact = self::TOTAL_ROWS * $dayCount / $totalDays;
            $counts[$year] = (int) floor($exact);
            $remainders[$year] = $exact - $counts[$year];
        }

        arsort($remainders);

        $short = self::TOTAL_ROWS - array_sum($counts);

        foreach (array_slice(array_keys($remainders), 0, $short) as $year) {
            $counts[$year]++;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * The profile flattened so each entry appears `weight` times.
     *
     * @return array<int, array{0: string, 1: int, 2: float, 3: float, 4: array<int, string>}>
     */
    private function weightedCategories(): array
    {
        $flat = [];

        foreach ($this->profile() as $entry) {
            $flat = array_merge($flat, array_fill(0, $entry[1], $entry));
        }

        return $flat;
    }

    /**
     * Bulk insert skips the model, so everything it would have filled in is
     * supplied here — uuid included.
     *
     * @return array<string, mixed>
     */
    private function row(int $userId, int $categoryId, string $item, float $price, CarbonImmutable $date): array
    {
        return [
            'uuid' => (string) Str::orderedUuid(),
            'user_id' => $userId,
            'category_id' => $categoryId,
            'item' => json_encode(['en' => $item], JSON_UNESCAPED_UNICODE),
            'price' => $price,
            'spent_on' => $date->toDateString(),
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }

    /**
     * Category ids keyed by English name, so the profile can name them.
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function categoryIds()
    {
        return Category::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $category) => [
                $category->getTranslation('name', 'en') => $category->id,
            ]);
    }
}
