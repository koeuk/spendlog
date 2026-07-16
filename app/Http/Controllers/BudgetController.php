<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetSummary $summary) {}

    public function index(Request $request): Response
    {
        $month = $this->resolveMonth($request->query('month'));

        return Inertia::render('Budgets/Index', [
            'summary' => $this->summary->forMonth($request->user(), $month),
            'month' => $month->format('Y-m'),
            'prev_month' => $month->subMonth()->format('Y-m'),
            'next_month' => $month->addMonth()->format('Y-m'),
            // Month names come from the server so they follow the app locale —
            // toLocaleDateString in the browser would follow the OS instead.
            'months' => $this->monthOptions(),
            'years' => $this->yearOptions($request->user(), $month),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function monthOptions(): array
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
     * @return array<int, int>
     */
    private function yearOptions(User $user, CarbonImmutable $viewing): array
    {
        $earliest = min(array_filter([
            Expense::where('user_id', $user->id)->min('spent_on'),
            Budget::where('user_id', $user->id)->min('month'),
        ]) ?: [CarbonImmutable::now()->toDateString()]);

        $first = (int) CarbonImmutable::parse($earliest)->year;
        $last = CarbonImmutable::now()->year;

        // The current month may sit outside that span — someone can navigate to
        // next year via the arrow, and the dropdown has to be able to show it.
        return range(
            min($first, $viewing->year),
            max($last, $viewing->year),
        );
    }

    /**
     * Set or update a budget — one endpoint, because the page always knows the
     * (category, month) slot and does not care whether a row exists yet.
     */
    public function store(BudgetRequest $request): RedirectResponse
    {
        $attributes = $request->budgetAttributes();

        DB::beginTransaction();

        try {
            $request->user()->budgets()->updateOrCreate(
                [
                    'category_id' => $attributes['category_id'],
                    'month' => $attributes['month'],
                ],
                ['amount' => $attributes['amount']],
            );

            DB::commit();

            return redirect()->back()->withSuccess(__('Budget saved successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        Gate::authorize('delete', $budget);

        DB::beginTransaction();

        try {
            $budget->delete();

            DB::commit();

            return redirect()->back()->withSuccess(__('Budget removed successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage());
        }
    }

    /**
     * Accepts 'YYYY-MM' from the query string; anything else falls back to the
     * current month rather than throwing.
     */
    private function resolveMonth(?string $month): CarbonImmutable
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            } catch (\Throwable) {
                // fall through
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
