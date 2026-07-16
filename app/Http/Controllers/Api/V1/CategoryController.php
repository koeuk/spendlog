<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        $categories = QueryBuilder::for(Category::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('color'),
            )
            ->allowedSorts(
                'name',
                AllowedSort::field('expenses', 'expenses_count'),
            )
            ->defaultSort('name')
            ->withCount('expenses')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('viewAny', Category::class);

        return new CategoryResource($category->loadCount('expenses'));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = DB::transaction(fn () => Category::create($request->validated()));

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        DB::transaction(fn () => $category->update($request->validated()));

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        try {
            DB::transaction(fn () => $category->delete());
        } catch (QueryException $e) {
            // 23000 is the SQLSTATE class for integrity constraint violations —
            // here, the restricting foreign key on expenses/budgets. 409 rather
            // than 500: the request was well-formed, the state just forbids it.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => __('":name" is still in use and cannot be deleted.', ['name' => $category->name]),
                ], 409);
            }

            throw $e;
        }

        return response()->json([], 204);
    }
}
