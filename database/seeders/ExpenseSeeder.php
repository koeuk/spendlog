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
 * Development spend for every user, across several full years.
 *
 * Deliberately not in DatabaseSeeder: that one runs on deploys, and inventing
 * expenses on a real installation would corrupt somebody's actual books. Reach
 * this through DemoDataSeeder, which refuses to run in production.
 *
 * Written with insert() in chunks rather than Eloquent creates — a few thousand
 * rows is a few thousand round trips otherwise. That means the model's hooks do
 * not fire, so uuid and the timestamps are supplied by hand below; HasUuidRouteKey
 * generates its uuid on `creating`, which bulk insert never reaches.
 *
 * The distribution is the point, not the individual rows. Every screen this feeds
 * reads differently depending on the shape, so it is built to exercise them:
 *
 *   - weighted categories, so "Where it went" has a clear head and a long tail
 *     rather than a row of equal bars
 *   - per-category price bands, so Rent never costs $2 and coffee never costs $450
 *   - a seeded RNG per user and year, so re-running produces the same figures and
 *     a screenshot taken yesterday still matches
 *   - a few sub-dollar amounts, which is where the four-decimal price column and
 *     the riel conversion actually show up
 *   - rows dated today, so "spent today" is not a zero that looks like a bug
 */
class ExpenseSeeder extends Seeder
{
    /** Expenses to create per user, per year. */
    private const PER_YEAR = 238;

    /** Inclusive range of years to fill. */
    private const FIRST_YEAR = 2024;

    private const LAST_YEAR = 2026;

    /** Rows written per INSERT. */
    private const CHUNK = 500;

    /**
     * [category, weight, min price, max price, items].
     *
     * Weight drives how often the category is picked, so the breakdown has a
     * shape. Rent is heavy by amount but light by count — it lands once a month
     * and dominates the money without dominating the list.
     *
     * @return array<int, array{0: string, 1: int, 2: float, 3: float, 4: array<int, string>}>
     */
    private function profile(): array
    {
        return [
            ['Food', 22, 2.50, 12.00, ['Lunch', 'Dinner', 'Street food', 'Noodles']],
            ['Coffee', 18, 0.50, 4.50, ['Morning coffee', 'Iced coffee', 'Iced tea']],
            ['Groceries', 12, 15.00, 85.00, ['Weekly shop', 'Market run', 'Top-up shop']],
            ['Transport', 10, 1.00, 45.00, ['Grab', 'Fuel', 'Tuk-tuk', 'Parking']],
            ['Beer', 8, 6.00, 95.00, ['Round with the team', 'Weekend', 'Night out']],
            ['Phone', 5, 3.00, 15.00, ['Top-up', 'Data pack']],
            ['Health', 4, 5.00, 60.00, ['Pharmacy', 'Check-up']],
            ['Fitness', 4, 15.00, 45.00, ['Gym', 'Class']],
            ['Entertainment', 4, 5.00, 40.00, ['Cinema', 'Streaming', 'Concert']],
            ['Shopping', 4, 10.00, 120.00, ['Clothes', 'Shoes', 'Homeware']],
            ['Bills', 3, 20.00, 90.00, ['Electricity', 'Water']],
            ['Travel', 2, 60.00, 320.00, ['Weekend trip', 'Bus ticket', 'Hotel']],
            ['Gifts', 2, 10.00, 80.00, ['Birthday', 'Wedding']],
            // Once a month, but the largest single amount — the money leader
            // without the row count.
            ['Rent', 1, 420.00, 480.00, ['Monthly rent']],
        ];
    }

    public function run(): void
    {
        $users = User::orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->command?->warn('ExpenseSeeder: no users — run UserSeeder first.');

            return;
        }

        $categoryIds = $this->categoryIds();

        if ($categoryIds->isEmpty()) {
            $this->command?->warn('ExpenseSeeder: no categories — run CategorySeeder first.');

            return;
        }

        // Wiped rather than added to, so re-running gives the same dashboard
        // instead of doubling every figure on it.
        Expense::whereIn('user_id', $users->pluck('id'))->delete();

        $rows = [];
        $written = 0;

        foreach ($users as $user) {
            foreach (range(self::FIRST_YEAR, self::LAST_YEAR) as $year) {
                foreach ($this->yearRows($user, $year, $categoryIds) as $row) {
                    $rows[] = $row;

                    if (count($rows) >= self::CHUNK) {
                        DB::table('expenses')->insert($rows);
                        $written += count($rows);
                        $rows = [];
                    }
                }
            }
        }

        if ($rows !== []) {
            DB::table('expenses')->insert($rows);
            $written += count($rows);
        }

        $this->command?->info(sprintf(
            'ExpenseSeeder: %d expenses across %d users, %d–%d.',
            $written,
            $users->count(),
            self::FIRST_YEAR,
            self::LAST_YEAR,
        ));
    }

    /**
     * One year of rows for one user.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $categoryIds
     * @return array<int, array<string, mixed>>
     */
    private function yearRows(User $user, int $year, $categoryIds): array
    {
        /*
         * Seeded per (user, year) so the data is reproducible. Without this, every
         * run rewrites every figure and no two screenshots of the same screen
         * agree — and "did my change do that, or did the seeder?" becomes
         * impossible to answer.
         */
        mt_srand($user->id * 1000 + $year);

        $start = CarbonImmutable::create($year, 1, 1);
        $end = $start->endOfYear();

        // The current year stops at today: ExpenseRequest rejects future dates,
        // so seeding them would build rows the app itself refuses to accept. The
        // count stays the same, compressed into the elapsed part of the year.
        $now = CarbonImmutable::now();

        if ($end->isAfter($now)) {
            $end = $now;
        }

        $span = max(1, $start->diffInDays($end));
        $weighted = $this->weightedCategories();
        $rows = [];

        // The current year reserves two of its allocation for today's rows below,
        // so every year lands on exactly PER_YEAR rather than the current one
        // quietly running two over.
        $isCurrentYear = $year === (int) $now->year;
        $random = self::PER_YEAR - ($isCurrentYear ? 2 : 0);

        for ($i = 0; $i < $random; $i++) {
            [$name, , $min, $max, $items] = $weighted[mt_rand(0, count($weighted) - 1)];

            $categoryId = $categoryIds[$name] ?? null;

            if (! $categoryId) {
                continue;
            }

            $date = $start->addDays(mt_rand(0, (int) $span));
            $item = $items[mt_rand(0, count($items) - 1)];

            $rows[] = $this->row($user->id, $categoryId, $item, $this->price($min, $max), $date);
        }

        // Guarantee something today for the current year, so the dashboard's
        // "spent today" is populated the moment the seed finishes.
        if ($isCurrentYear) {
            $rows[] = $this->row($user->id, $categoryIds['Coffee'] ?? $categoryIds->first(), 'Morning coffee', 2.50, $now);
            $rows[] = $this->row($user->id, $categoryIds['Food'] ?? $categoryIds->first(), 'Lunch', 6.75, $now);
        }

        return $rows;
    }

    /**
     * A price in the band, occasionally sub-dollar with four decimals.
     *
     * The odd fractional amount is not decoration: at two decimals a riel figure
     * snaps to multiples of ~41៛, which is the case the price column widened for.
     */
    private function price(float $min, float $max): float
    {
        if ($min < 1.0 && mt_rand(1, 4) === 1) {
            // The shape a riel amount takes once converted — 2000៛ at 4100.
            return round(mt_rand(500, 3000) / 4100, 4);
        }

        return round(mt_rand((int) ($min * 100), (int) ($max * 100)) / 100, 2);
    }

    /**
     * The profile flattened so each entry appears `weight` times — picking one at
     * random then honours the weighting without any cumulative-sum arithmetic.
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
            // The translatable column is JSON on the way in; no accessor runs on
            // a bulk insert, so it is encoded here.
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
