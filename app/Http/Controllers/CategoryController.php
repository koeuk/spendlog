<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\TranslatableQuery;
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

        $categories = QueryBuilder::for(Category::class)
            ->allowedFilters(
                // Keyed 'name' rather than "name->{locale}": the query string is
                // then the same in every language, and a Khmer name stays
                // findable while the UI is in English.
                TranslatableQuery::filter('name'),
                AllowedFilter::exact('color'),
            )
            ->allowedSorts(
                TranslatableQuery::sort('name'),
                AllowedSort::field('expenses', 'expenses_count'),
                AllowedSort::field('created', 'created_at'),
            )
            /*
             * Newest first: a category is added to be used straight away, so the
             * one just created is the one being looked for.
             *
             * Passed as instances, not as the string '-created': defaultSort()
             * builds its own AllowedSort from a string and would map the alias
             * back onto a literal `created` column, which does not exist. The
             * leading '-' sets the direction; the second argument keeps the real
             * column. Ties break on id because a seed or import shares a
             * created_at to the second, and MySQL may then order those rows
             * differently on every query.
             */
            ->defaultSort(
                AllowedSort::field('-created', 'created_at'),
                AllowedSort::field('-id'),
            )
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
            // Cast: only() returns [] when nothing is set, which serialises to a
            // JSON array — and filters.filter in JS then finds Array.prototype's
            // own filter() rather than undefined. Its .name is the string
            // "filter", which is how that word appeared in the search box.
            'filters' => (object) request()->only('filter', 'sort'),
            // One flag per verb: 'create' no longer implies 'update' now that a
            // normal user can add a category inline but not edit a shared one.
            'can' => [
                'create' => Gate::allows('create', Category::class),
                'update' => Gate::allows('update', new Category),
                'delete' => Gate::allows('delete', new Category),
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
