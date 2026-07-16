<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
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
        $expenses = QueryBuilder::for(Expense::class)
            ->allowedFilters([
                AllowedFilter::partial('item'),
                // Filter by the public UUID; the column itself stays internal.
                AllowedFilter::callback('category', fn ($query, $value) => $query->whereHas(
                    'category',
                    fn ($q) => $q->whereIn('uuid', (array) $value),
                )),
                AllowedFilter::callback('from', fn ($query, $value) => $query->whereDate('spent_on', '>=', $value)),
                AllowedFilter::callback('to', fn ($query, $value) => $query->whereDate('spent_on', '<=', $value)),
            ])
            ->allowedSorts(['spent_on', 'price', 'item'])
            ->defaultSort('-spent_on', '-id')
            ->with(['category:id,uuid,name,color,icon', 'user:id,uuid,name'])
            // Scoped last so no filter can widen it beyond the owner's rows.
            ->where('user_id', $request->user()->id)
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Expenses/Index', [
            'days' => $this->groupByDay($expenses->items()),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'prev_page_url' => $expenses->previousPageUrl(),
                'next_page_url' => $expenses->nextPageUrl(),
                'total' => $expenses->total(),
            ],
            'filters' => $request->only('filter', 'sort'),
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['uuid', 'name', 'color', 'icon']),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
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
    private function groupByDay(array $expenses): array
    {
        return collect($expenses)
            ->groupBy(fn (Expense $expense) => $expense->spent_on->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'total' => (float) $group->sum('price'),
                'expenses' => $group->map(fn (Expense $expense) => [
                    'uuid' => $expense->uuid,
                    'item' => $expense->item,
                    'price' => (float) $expense->price,
                    'spent_on' => $expense->spent_on->toDateString(),
                    'category_uuid' => $expense->category->uuid,
                    'category' => $expense->category->name,
                    'category_color' => $expense->category->color?->value,
                    'category_icon' => $expense->category->icon?->value,
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
