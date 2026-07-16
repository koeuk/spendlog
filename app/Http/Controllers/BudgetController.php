<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    /**
     * Set or update a budget — one endpoint, because the page always knows the
     * (category, month) slot and does not care whether a row exists yet.
     */
    public function store(BudgetRequest $request): RedirectResponse
    {
        $attributes = $request->budgetAttributes();

        $request->user()->budgets()->updateOrCreate(
            [
                'category_id' => $attributes['category_id'],
                'month' => $attributes['month'],
            ],
            ['amount' => $attributes['amount']],
        );

        return back()->with('success', 'Budget saved.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        Gate::authorize('delete', $budget);

        $budget->delete();

        return back()->with('success', 'Budget removed.');
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
