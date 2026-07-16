<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Support\TranslatableQuery;
use App\Models\User;
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
    public function index(Request $request): Response
    {
        $isAdmin = $request->user()->isAdmin();
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

        $expenses = $query->paginate(50)->withQueryString();

        return Inertia::render('Expenses/Index', [
            'days' => $this->groupByDay($expenses->items(), $viewingAll),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'prev_page_url' => $expenses->previousPageUrl(),
                'next_page_url' => $expenses->nextPageUrl(),
                'total' => $expenses->total(),
            ],
            'filters' => $request->only('filter', 'sort'),
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

            return redirect()->back()->withError($e->getMessage())->withInput();
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

            return redirect()->back()->withError($e->getMessage())->withInput();
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

            return redirect()->back()->withError($e->getMessage());
        }
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
