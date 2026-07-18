<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Development budgets for every user, every month ExpenseSeeder covers.
 *
 * Not in DatabaseSeeder — see ExpenseSeeder for why. Reach it through
 * DemoDataSeeder.
 *
 * A budget only means something next to spend, so the amounts are pitched
 * against what ExpenseSeeder actually logs: the overall figure sits below a
 * typical month so the over-budget banner is visible, and the per-category ones
 * are spread so that every progress state — ok, warning, over, and budgeted-but
 * unspent — is on screen at once. A set where everything reads 40% tells you
 * nothing about whether the colour thresholds work.
 */
class BudgetSeeder extends Seeder
{
    private const FIRST_YEAR = 2024;

    private const LAST_YEAR = 2026;

    /**
     * [category or null for the overall budget, amount].
     *
     * @return array<int, array{0: string|null, 1: float}>
     */
    private function budgets(): array
    {
        return [
            // Overall. A typical seeded month runs past this, so the app opens
            // with the banner showing rather than needing a row hand-edited.
            [null, 800.00],

            ['Rent', 450.00],
            // Tight against a heavy category, so this one usually reads "over".
            ['Beer', 150.00],
            ['Groceries', 220.00],
            ['Food', 180.00],
            ['Coffee', 60.00],
            ['Transport', 120.00],
            ['Fitness', 100.00],
            // Rarely spent against — the zero-progress row.
            ['Gifts', 50.00],
        ];
    }

    public function run(): void
    {
        $users = User::orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->command?->warn('BudgetSeeder: no users — run UserSeeder first.');

            return;
        }

        $categoryIds = Category::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Category $c) => [$c->getTranslation('name', 'en') => $c->id]);

        Budget::whereIn('user_id', $users->pluck('id'))->delete();

        $months = $this->months();
        $rows = [];

        foreach ($users as $user) {
            foreach ($months as $month) {
                foreach ($this->budgets() as [$name, $amount]) {
                    // Null category is the overall budget — the convention
                    // BudgetSummary reads, not a failed lookup.
                    $categoryId = $name === null ? null : ($categoryIds[$name] ?? null);

                    if ($name !== null && $categoryId === null) {
                        continue;
                    }

                    $rows[] = [
                        'uuid' => (string) Str::orderedUuid(),
                        'user_id' => $user->id,
                        'category_id' => $categoryId,
                        'amount' => $amount,
                        'month' => $month->toDateString(),
                        'created_at' => $month,
                        'updated_at' => $month,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('budgets')->insert($chunk);
        }

        $this->command?->info(sprintf(
            'BudgetSeeder: %d budgets across %d users, %d months.',
            count($rows),
            $users->count(),
            count($months),
        ));
    }

    /**
     * Every month in the seeded range, stopping at the current one — a budget for
     * a month that has not happened yet is noise in the picker.
     *
     * @return array<int, CarbonImmutable>
     */
    private function months(): array
    {
        $month = CarbonImmutable::create(self::FIRST_YEAR, 1, 1);
        $last = CarbonImmutable::create(self::LAST_YEAR, 12, 1)->startOfMonth();
        $now = CarbonImmutable::now()->startOfMonth();

        if ($last->isAfter($now)) {
            $last = $now;
        }

        $months = [];

        while (! $month->isAfter($last)) {
            $months[] = $month;
            $month = $month->addMonth();
        }

        return $months;
    }
}
