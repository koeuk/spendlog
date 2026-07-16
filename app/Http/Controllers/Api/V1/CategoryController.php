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

/**
 * @group Categories
 *
 * The shared taxonomy every expense and budget hangs off. Readable by everyone,
 * writable by admins only — and a token needs `categories:write` on top of that.
 *
 * @authenticated
 */
class CategoryController extends Controller
{
    /**
     * List categories
     *
     * @queryParam filter[name] string Partial match. Example: foo
     * @queryParam filter[color] string Exact colour. Example: amber
     * @queryParam sort string name or expenses. Prefix with - to reverse. Example: -expenses
     *
     * @response 200 {"data": [{"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils", "expenses_count": 12, "created_at": "2026-07-16T10:00:00+00:00", "updated_at": "2026-07-16T10:00:00+00:00"}]}
     */
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

    /**
     * Get a category
     *
     * @urlParam category string required The category UUID. Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils", "expenses_count": 12}}
     * @response 404 scenario="unknown or non-UUID" {"message": "Not found."}
     */
    public function show(Category $category): CategoryResource
    {
        Gate::authorize('viewAny', Category::class);

        return new CategoryResource($category->loadCount('expenses'));
    }

    /**
     * Create a category
     *
     * Admins only, and the token needs `categories:write` — which is **not**
     * granted by default.
     *
     * @bodyParam name string required Must be unique. Example: Travel
     * @bodyParam color string required One of: slate, red, orange, amber, green, teal, blue, indigo, purple, pink. Example: blue
     * @bodyParam icon string One of the CategoryIcon values, or null. Example: plane
     *
     * @response 201 {"data": {"uuid": "0198a...", "name": "Travel", "color": "blue", "icon": "plane"}}
     * @response 403 scenario="not an admin, or token lacks categories:write" {"message": "This action is unauthorized."}
     * @response 422 scenario="duplicate name" {"message": "The name has already been taken.", "errors": {"name": ["The name has already been taken."]}}
     */
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
