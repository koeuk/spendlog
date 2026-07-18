<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Enums\Permission;
use App\Models\Category;
use App\Models\Expense;
use App\Support\TranslatableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Expenses
 *
 * Daily expense tracking. Every listing is scoped to the caller's own rows
 * unless an admin explicitly opts out with `scope=all`.
 *
 * @authenticated
 */
class ExpenseController extends Controller
{
    /** Matches the web list; ?per_page can narrow it for a phone screen. */
    private const PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    /**
     * List expenses
     *
     * Paginated, newest first.
     *
     * `filter[user]` exists only while an admin is viewing everyone; for anyone
     * else it is a 400, not a silently empty result.
     *
     * @queryParam filter[item] string Partial match on the item name. Example: coffee
     * @queryParam filter[category] string Category UUID. Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     * @queryParam filter[from] date Only expenses on or after this day. Example: 2026-07-01
     * @queryParam filter[to] date Only expenses on or before this day. Example: 2026-07-31
     * @queryParam filter[user] string Admin only, and only with scope=all. User UUID.
     * @queryParam sort string spent_on, price or item. Prefix with - to reverse. Example: -price
     * @queryParam scope string Admin only. Set to "all" to list every user's expenses and include `owner`. Example: all
     * @queryParam per_page int Default 50, clamped to 100. Example: 25
     *
     * @response 200 scenario="success" {"data": [{"uuid": "0198f...", "item": "Coffee", "item_translations": {"en": "Coffee"}, "price": "4.50", "spent_on": "2026-07-16", "category": {"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils"}, "created_at": "2026-07-16T10:00:00+00:00", "updated_at": "2026-07-16T10:00:00+00:00"}], "links": {"first": "...", "last": "...", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 50, "total": 1}}
     * @response 400 scenario="non-admin used filter[user]" {"message": "Requested filter(s) `user` are not allowed."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
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
            ->with('category');

        // The owner is only loaded where it is shown, so a normal listing never
        // pays for the join.
        if ($viewingAll) {
            $query->with('user');
        }

        // Applied last so no filter can widen it beyond the owner's rows.
        if (! $viewingAll) {
            $query->where('user_id', $request->user()->id);
        }

        return ExpenseResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    /**
     * Get an expense
     *
     * @urlParam expense string required The expense UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 200 {"data": {"uuid": "0198f...", "item": "Coffee", "item_translations": {"en": "Coffee"}, "price": "4.50", "spent_on": "2026-07-16", "category": {"uuid": "0198a...", "name": "Food", "color": "amber", "icon": "utensils"}}}
     * @response 403 scenario="someone else's expense" {"message": "This action is unauthorized."}
     * @response 404 scenario="unknown or non-UUID" {"message": "Not found."}
     */
    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('view', $expense);

        return new ExpenseResource($expense->load('category'));
    }

    /**
     * Create an expense
     *
     * The owner always comes from the token — a `user_id` in the payload is
     * ignored, not honoured.
     *
     * The item is translatable, so it is sent as a per-locale map rather than a
     * string. English is the fallback locale and is therefore required.
     *
     * @bodyParam item object required Per-locale item names.
     * @bodyParam item.en string required Example: Coffee
     * @bodyParam item.km string Optional Khmer name. Example: កាហ្វេ
     * @bodyParam price number required Max 99999999.99. Example: 4.50
     * @bodyParam category_uuid string required Must be an existing category. Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     * @bodyParam spent_on date required Cannot be in the future. Example: 2026-07-16
     *
     * @response 201 {"data": {"uuid": "0198f...", "item": "Coffee", "item_translations": {"en": "Coffee"}, "price": "4.50", "spent_on": "2026-07-16", "category": {"uuid": "0198a...", "name": "Food"}}}
     * @response 403 scenario="token lacks expenses:write" {"message": "Invalid ability provided."}
     * @response 422 scenario="future date" {"message": "You cannot log an expense in the future.", "errors": {"spent_on": ["You cannot log an expense in the future."]}}
     */
    public function store(ExpenseRequest $request): JsonResponse
    {
        Gate::authorize('create', Expense::class);

        // Naming a category inline creates one, so it goes through the same gate
        // the Categories endpoints use. The token's expenses:write ability says
        // nothing about categories, and ExpenseRequest::resolveCategoryId() will
        // firstOrCreate one without asking.
        $this->authorizeInlineCategory($request);

        // Created through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $expense = DB::transaction(
            fn () => $request->user()->expenses()->create($request->expenseAttributes())
        );

        return (new ExpenseResource($expense->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an expense
     *
     * Admins may edit anyone's; everyone else only their own.
     *
     * @urlParam expense string required The expense UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam item object required Per-locale item names.
     * @bodyParam item.en string required Example: Coffee
     * @bodyParam item.km string Optional Khmer name. Example: កាហ្វេ
     * @bodyParam price number required Example: 4.50
     * @bodyParam category_uuid string required Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     * @bodyParam spent_on date required Cannot be in the future. Example: 2026-07-16
     *
     * @response 200 {"data": {"uuid": "0198f...", "item": "Coffee", "item_translations": {"en": "Coffee"}, "price": "4.50", "spent_on": "2026-07-16"}}
     * @response 403 scenario="someone else's expense" {"message": "This action is unauthorized."}
     */
    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        Gate::authorize('update', $expense);
        $this->authorizeInlineCategory($request);

        DB::transaction(fn () => $expense->update($request->expenseAttributes()));

        return new ExpenseResource($expense->load('category'));
    }

    /**
     * Delete an expense
     *
     * @urlParam expense string required The expense UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's expense" {"message": "This action is unauthorized."}
     */
    public function destroy(Expense $expense): JsonResponse
    {
        Gate::authorize('delete', $expense);

        DB::transaction(fn () => $expense->delete());

        return response()->json([], 204);
    }

    /**
     * Gate the category an inline `new_category` would create.
     *
     * Mirrors the web ExpenseController: the dialog and the API both let a name
     * stand in for a uuid, and both have to ask CategoryPolicy before that name
     * becomes a row every other user can see.
     */
    private function authorizeInlineCategory(ExpenseRequest $request): void
    {
        if (filled($request->input('new_category'))) {
            Gate::authorize('create', Category::class);
        }
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', self::PER_PAGE);

        // Clamped so a client cannot ask for the whole table in one call.
        return max(1, min($requested, self::MAX_PER_PAGE));
    }
}
