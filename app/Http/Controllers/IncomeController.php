<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Models\Income;
use App\Support\CalendarOptions;
use App\Support\Concerns\PaginatesLists;
use App\Support\Concerns\ValidatesDateFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The Income page — the web counterpart to Api\V1\IncomeController.
 *
 * Own rows only, like the API: there is no Everyone view here. An admin holding
 * incomes.manage_all can still edit or delete a single row by its URL, which is
 * what the policy allows, but nothing on this page lists other people's money.
 */
class IncomeController extends Controller
{
    use PaginatesLists, ValidatesDateFilters;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Income::class);

        $query = QueryBuilder::for(Income::class)
            ->allowedFilters(AllowedFilter::partial('source'))
            ->allowedSorts('received_on', 'amount', 'source')
            ->defaultSort('-received_on', '-id')
            // Applied before the date filters and after the spatie ones, so no
            // filter can widen the list beyond the owner's rows.
            ->where('user_id', $request->user()->id);

        $monthFilter = $this->validMonth($request->query('month'));
        $yearFilter = $this->validYear($request->query('year'));

        if ($monthFilter !== '') {
            $query->whereMonth('received_on', (int) $monthFilter);
        }

        if ($yearFilter !== '') {
            $query->whereYear('received_on', (int) $yearFilter);
        }

        $incomes = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('Incomes/Index', [
            'days' => $this->groupByDay($incomes->items()),
            'pagination' => $this->paginationMeta($incomes),
            // Cast for the same reason as ExpenseController@index: an empty
            // only() is a JSON array, and filters.filter then resolves to
            // Array.prototype.filter instead of undefined.
            'filters' => (object) $request->only('filter', 'sort'),
            'month' => $monthFilter,
            'year' => $yearFilter,
            // Month names come from the server so they follow the app locale.
            'months' => CalendarOptions::months(),
            'years' => $this->yearOptions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Income::class);

        return Inertia::render('Incomes/Form', [
            'sources' => $this->sourceOptions($request),
            'return_query' => $this->returnQuery($request),
        ]);
    }

    public function edit(Request $request, Income $income): Response
    {
        Gate::authorize('update', $income);

        return Inertia::render('Incomes/Form', [
            'income' => [
                'uuid' => $income->uuid,
                'source' => $income->source,
                'amount' => (float) $income->amount,
                'received_on' => $income->received_on?->toDateString(),
                'note' => $income->note,
            ],
            'sources' => $this->sourceOptions($request),
            'return_query' => $this->returnQuery($request),
        ]);
    }

    public function store(IncomeRequest $request): RedirectResponse
    {
        Gate::authorize('create', Income::class);

        try {
            // Created through the relationship so user_id is never mass-assignable.
            DB::transaction(fn () => $request->user()->incomes()->create($request->incomeAttributes()));
        } catch (\Throwable $e) {
            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        // Not back(): the form is its own page, so back() would land on the
        // form that was just submitted. returnQuery puts the list back on the
        // month it came from.
        return redirect()
            ->route('incomes.index', $this->returnQuery($request))
            ->withSuccess(__('Income added successfully.'));
    }

    public function update(IncomeRequest $request, Income $income): RedirectResponse
    {
        Gate::authorize('update', $income);

        try {
            DB::transaction(fn () => $income->update($request->incomeAttributes()));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('incomes.index', $this->returnQuery($request))
            ->withSuccess(__('Income updated successfully.'));
    }

    public function destroy(Income $income): RedirectResponse
    {
        Gate::authorize('delete', $income);

        try {
            DB::transaction(fn () => $income->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        return redirect()->back()->withSuccess(__('Income deleted successfully.'));
    }

    /**
     * Where the list was when the form was opened, so saving returns to the
     * same month rather than to an unfiltered list.
     *
     * Whitelisted by key and revalidated, never echoed — the same reasoning as
     * ExpenseController::returnQuery(). Only month and year exist here: there
     * is no scope or user filter on this page.
     */
    private function returnQuery(Request $request): array
    {
        $source = is_array($request->input('return_query'))
            ? $request->input('return_query')
            : $request->query();

        return array_filter([
            'month' => $this->validMonth($source['month'] ?? null),
            'year' => $this->validYear($source['year'] ?? null),
        ], fn (string $value) => $value !== '');
    }

    /**
     * The sources this person has used, most frequent first, for the picker.
     *
     * A source is only ever the string on each row — there is no table to read
     * a catalogue from — so the list is whatever has been typed before. Per
     * person, like the rows themselves.
     *
     * @return array<int, string>
     */
    private function sourceOptions(Request $request): array
    {
        return Income::query()
            ->forUser($request->user()->id)
            ->groupBy('source')
            ->selectRaw('source, COUNT(*) as uses')
            ->orderByDesc('uses')
            ->orderBy('source')
            ->pluck('source')
            ->all();
    }

    /**
     * The years worth offering, from the first income to now, newest first.
     *
     * @return array<int, int>
     */
    private function yearOptions(Request $request): array
    {
        $earliest = Income::query()
            ->forUser($request->user()->id)
            ->min('received_on');

        $current = CarbonImmutable::now()->year;
        $first = $earliest ? (int) CarbonImmutable::parse($earliest)->year : $current;

        return range($current, min($first, $current));
    }

    /**
     * Shape the flat list into the daily-grouped structure the page renders.
     *
     * @param  array<int, Income>  $incomes
     */
    private function groupByDay(array $incomes): array
    {
        return collect($incomes)
            ->groupBy(fn (Income $income) => $income->received_on->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'total' => (float) $group->sum('amount'),
                'incomes' => $group->map(fn (Income $income) => [
                    'uuid' => $income->uuid,
                    'source' => $income->source,
                    'amount' => (float) $income->amount,
                    'received_on' => $income->received_on->toDateString(),
                    'note' => $income->note,
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
