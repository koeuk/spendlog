<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $locale = app()->getLocale();

        $categories = QueryBuilder::for(Category::class)
            ->allowedFilters(
                // name is JSON, so filter/sort target the active locale's key
                // rather than the column as a whole.
                AllowedFilter::partial("name->{$locale}"),
                AllowedFilter::exact('color'),
            )
            ->allowedSorts(
                AllowedSort::field('name', "name->{$locale}"),
                AllowedSort::field('expenses', 'expenses_count'),
            )
            ->defaultSort('name')
            ->withCount('expenses')
            ->get()
            ->map(fn (Category $category) => [
                'uuid' => $category->uuid,
                // The raw JSON field: {"en": "Food", "km": "អាហារ"}. This page
                // both displays and edits it, so it ships every locale and the
                // frontend picks one — no second, resolved copy of the name.
                'name' => $category->getTranslations('name'),
                'color' => $category->color?->value,
                'icon' => $category->icon?->value,
                'expenses_count' => $category->expenses_count,
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => request()->only('filter', 'sort'),
            'can' => [
                'manage' => Gate::allows('create', Category::class),
            ],
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        DB::beginTransaction();

        try {
            Category::create($request->categoryAttributes());

            DB::commit();

            return redirect()->back()->withSuccess(__('Category created successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        DB::beginTransaction();

        try {
            $category->update($request->categoryAttributes());

            DB::commit();

            return redirect()->back()->withSuccess(__('Category updated successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        DB::beginTransaction();

        try {
            $category->delete();

            DB::commit();

            return redirect()->back()->withSuccess(__('Category deleted successfully.'));
        } catch (QueryException $e) {
            DB::rollback();

            // The expenses/budgets foreign keys restrict on delete — show the
            // reason rather than the raw SQL error.
            return redirect()->back()->withError(
                __('":name" is still in use and cannot be deleted.', ['name' => $category->name])
            );
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage());
        }
    }
}
