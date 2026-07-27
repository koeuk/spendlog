<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\TranslatableQuery;
use Illuminate\Database\Eloquent\Builder;
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

        /*
         * created_at alone is not an order: the seeded categories share two
         * timestamps between 45 of them, and MySQL is free to return tied rows
         * in a different order on every query — the list would reshuffle on a
         * plain reload. id breaks the tie in whichever direction was asked for,
         * so "oldest" is the exact mirror of "newest" and both are stable.
         *
         * One instance, used as both an allowed and the default sort: the alias
         * has to keep its 'created_at' mapping, which a bare defaultSort('-created')
         * string would lose by rebuilding the sort against a `created` column
         * that does not exist. The leading '-' sets the default direction, and an
         * explicit ?sort=created still overrides it.
         */
        $created = AllowedSort::callback(
            '-created',
            function (Builder $query, bool $descending): void {
                $direction = $descending ? 'desc' : 'asc';

                $query->orderBy('created_at', $direction)->orderBy('id', $direction);
            },
        );

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
                $created,
            )
            // Newest first: a category is added to be used straight away, so the
            // one just created is the one being looked for.
            ->defaultSort($created)
            ->withCount('expenses')
            ->get()
            ->map(fn (Category $category) => [
                'uuid' => $category->uuid,
                // Resolved for the reader's locale, falling back to English. The
                // column is still translatable JSON, but the form edits one name
                // rather than one per language, so the page has no use for the
                // raw map any more.
                'name' => $category->name,
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

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('Categories/Form');
    }

    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('Categories/Form', [
            // One name, resolved for the reader's locale — the form is a single
            // box now, not a tab per language.
            'category' => [
                'uuid' => $category->uuid,
                'name' => $category->name,
                'color' => $category->color?->value,
                'icon' => $category->icon?->value,
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

            return redirect()
                ->route('categories.index')
                ->withSuccess(__('Category created successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        DB::beginTransaction();

        try {
            $category->update($request->categoryAttributes());

            DB::commit();

            return redirect()
                ->route('categories.index')
                ->withSuccess(__('Category updated successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
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

            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }
    }
}
