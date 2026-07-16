<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('Categories/Index', [
            'categories' => Category::query()
                ->withCount('expenses')
                ->orderBy('name')
                ->get(),
            'can' => [
                'manage' => Gate::allows('create', Category::class),
            ],
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        Category::create($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        try {
            $category->delete();
        } catch (QueryException) {
            // The expenses/budgets foreign keys restrict on delete.
            return back()->with('error', "\"{$category->name}\" is still in use and cannot be deleted.");
        }

        return back()->with('success', 'Category deleted.');
    }
}
