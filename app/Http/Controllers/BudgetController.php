<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetSummary;
use App\Support\CalendarOptions;
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
        Gate::authorize('viewAny', Budget::class);

        $month = CalendarOptions::resolveMonth($request->query('month'));

        return Inertia::render('Budgets/Index', [
            'summary' => $this->summary->forMonth($request->user(), $month),
            'month' => $month->format('Y-m'),
            'prev_month' => $month->subMonth()->format('Y-m'),
            'next_month' => $month->addMonth()->format('Y-m'),
            // Month names come from the server so they follow the app locale —
            // toLocaleDateString in the browser would follow the OS instead.
            'months' => CalendarOptions::months(),
            'years' => CalendarOptions::years($request->user(), $month),
        ]);
    }

    /**
     * Set or update a budget — one endpoint, because the page always knows the
     * (category, month) slot and does not care whether a row exists yet.
     */
    public function store(BudgetRequest $request): RedirectResponse
    {
        // BudgetRequest::authorize() only settles that there is no cross-user
        // target here, which is a different question from whether this user may
        // write budgets at all. Without this the page could 403 while the
        // endpoint behind it still accepted writes. Same ability as the API's
        // store, so the two surfaces answer identically.
        Gate::authorize('create', Budget::class);

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

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
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

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }
    }

    /**
     * Accepts 'YYYY-MM' from the query string; anything else falls back to the
     * current month rather than throwing.
     */
}
