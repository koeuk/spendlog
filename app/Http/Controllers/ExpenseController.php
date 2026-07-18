<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Support\CalendarOptions;
use App\Support\Concerns\PaginatesLists;
use App\Support\TranslatableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExpenseController extends Controller
{
    use PaginatesLists;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Expense::class);

        // The permission, not the role: granting expenses.view_all to a
        // non-admin has to actually open the Everyone view.
        $isAdmin = $request->user()->hasPermissionTo(Permission::ExpensesViewAll->value);
        // Only an admin can opt out of the owner scope, and only explicitly.
        $viewingAll = $isAdmin && $request->query('scope') === 'all';

        $filters = [
            TranslatableQuery::filter('item'),
            // Filter by the public UUID; the column itself stays internal.
            AllowedFilter::callback('category', fn ($query, $value) => $query->whereHas(
                'category',
                fn ($q) => $q->whereIn('uuid', (array) $value),
            )),
            AllowedFilter::callback('from', fn ($query, $value) => $query->whereDate('spent_on', '>=', $value)),
            AllowedFilter::callback('to', fn ($query, $value) => $query->whereDate('spent_on', '<=', $value)),
        ];

        // The user filter only exists while viewing everyone, so a non-admin
        // cannot use it to probe for other people's rows.
        if ($viewingAll) {
            $filters[] = AllowedFilter::callback('user', fn ($query, $value) => $query->whereHas(
                'user',
                fn ($q) => $q->whereIn('uuid', (array) $value),
            ));
        }

        $query = QueryBuilder::for(Expense::class)
            ->allowedFilters(...$filters)
            ->allowedSorts('spent_on', 'price', TranslatableQuery::sort('item'))
            ->defaultSort('-spent_on', '-id')
            ->with(['category:id,uuid,name,color,icon', 'user:id,uuid,name']);

        // Applied last so no filter can widen it beyond the owner's rows.
        if (! $viewingAll) {
            $query->where('user_id', $request->user()->id);
        }

        /*
         * Month and year are two independent controls, so each stands on its own:
         * a year alone shows that whole year, a month alone shows that month in
         * every year, and together they pin one month. Junk is ignored rather
         * than 500ing — these are query strings, not a form.
         */
        $monthFilter = $this->validMonth($request->query('month'));
        $yearFilter = $this->validYear($request->query('year'));

        if ($monthFilter !== '') {
            $query->whereMonth('spent_on', (int) $monthFilter);
        }

        if ($yearFilter !== '') {
            $query->whereYear('spent_on', (int) $yearFilter);
        }

        $expenses = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('Expenses/Index', [
            'days' => $this->groupByDay($expenses->items(), $viewingAll),
            'pagination' => $this->paginationMeta($expenses),
            // Cast for the same reason as CategoryController@index: an empty
            // only() is a JSON array, and filters.filter then resolves to
            // Array.prototype.filter instead of undefined.
            'filters' => (object) $request->only('filter', 'sort'),
            // Seed the two date selects so they reflect what is active on reload.
            'month' => $monthFilter,
            'year' => $yearFilter,
            // Month names come from the server so they follow the app locale —
            // toLocaleDateString in the browser would follow the OS instead.
            'months' => CalendarOptions::months(),
            'years' => $this->yearOptions($request, $viewingAll),
            'scope' => $viewingAll ? 'all' : 'mine',
            'can' => [
                'view_all' => $isAdmin,
                'create_category' => $request->user()->can('create', Category::class),
            ],
            // Only an admin viewing everyone needs the user list.
            'users' => $viewingAll
                ? User::query()->orderBy('name')->get(['uuid', 'name'])
                : [],
            // Mapped rather than passed straight through: Inertia serialises via
            // toArray(), and spatie/laravel-translatable does not translate there —
            // the raw {"en":…,"km":…} object would reach the category dropdown.
            // Reading ->name goes through the accessor, which does translate.
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['uuid', 'name', 'color', 'icon'])
                ->map(fn (Category $category) => [
                    'uuid' => $category->uuid,
                    'name' => $category->name,
                    'color' => $category->color?->value,
                    'icon' => $category->icon?->value,
                ]),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        Gate::authorize('create', Expense::class);

        // Naming a category inline creates one, so it goes through the same gate
        // the Categories page uses rather than trusting the dialog to hide itself.
        if (filled($request->input('new_category'))) {
            Gate::authorize('create', Category::class);
        }

        DB::beginTransaction();

        try {
            // Created through the relationship so user_id is never mass-assignable.
            $request->user()->expenses()->create($request->expenseAttributes());

            DB::commit();

            return redirect()->back()->withSuccess(__('Expense added successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        if (filled($request->input('new_category'))) {
            Gate::authorize('create', Category::class);
        }

        DB::beginTransaction();

        try {
            $expense->update($request->expenseAttributes());

            DB::commit();

            return redirect()->back()->withSuccess(__('Expense updated successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        DB::beginTransaction();

        try {
            $expense->delete();

            DB::commit();

            return redirect()->back()->withSuccess(__('Expense deleted successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }
    }

    /** '01'–'12', or '' for "every month". */
    private function validMonth(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^(0[1-9]|1[0-2])$/', $value) === 1 ? $value : '';
    }

    /** A four-digit year, or '' for "every year". */
    private function validYear(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^\d{4}$/', $value) === 1 ? $value : '';
    }

    /**
     * The years worth offering, drawn from the rows the viewer can actually see —
     * the same reasoning the Budgets page uses: listing 1990 when the first
     * expense is from 2024 is just a longer list to scroll.
     *
     * Newest first, because the current year is the one usually wanted.
     *
     * @return array<int, int>
     */
    private function yearOptions(Request $request, bool $viewingAll): array
    {
        $earliest = Expense::query()
            ->when(! $viewingAll, fn ($query) => $query->where('user_id', $request->user()->id))
            ->min('spent_on');

        $current = CarbonImmutable::now()->year;
        $first = $earliest ? (int) CarbonImmutable::parse($earliest)->year : $current;

        // An expense cannot be dated in the future, so $first never exceeds the
        // current year — min() keeps the range descending even if that changes.
        return range($current, min($first, $current));
    }

    /**
     * Shape the flat list into the daily-grouped structure the page renders.
     *
     * @param  array<int, Expense>  $expenses
     */
    private function groupByDay(array $expenses, bool $withOwner = false): array
    {
        return collect($expenses)
            ->groupBy(fn (Expense $expense) => $expense->spent_on->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'total' => (float) $group->sum('price'),
                'expenses' => $group->map(fn (Expense $expense) => [
                    'uuid' => $expense->uuid,
                    // Two shapes on purpose: the list renders the active locale,
                    // while the edit dialog has to populate a field per locale.
                    'item' => $expense->item,
                    'item_translations' => $expense->getTranslations('item'),
                    'price' => (float) $expense->price,
                    'spent_on' => $expense->spent_on->toDateString(),
                    'category_uuid' => $expense->category->uuid,
                    'category' => $expense->category->name,
                    'category_color' => $expense->category->color?->value,
                    'category_icon' => $expense->category->icon?->value,
                    // Only surfaced in the admin's everyone view.
                    'owner' => $withOwner ? $expense->user->name : null,
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
