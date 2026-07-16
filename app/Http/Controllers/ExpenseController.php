<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $expenses = Expense::query()
            ->with(['category:id,uuid,name,color,icon', 'user:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
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
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['uuid', 'name', 'color', 'icon']),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        // Created through the relationship so user_id is never mass-assignable.
        $request->user()->expenses()->create($request->expenseAttributes());

        return back()->with('success', 'Expense added.');
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $expense->update($request->expenseAttributes());

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        return back()->with('success', 'Expense deleted.');
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
