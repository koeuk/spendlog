<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExpenseController extends Controller
{
    /** Matches the web list; ?per_page can narrow it for a phone screen. */
    private const PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $isAdmin = $request->user()->isAdmin();
        // Only an admin can opt out of the owner scope, and only explicitly.
        $viewingAll = $isAdmin && $request->query('scope') === 'all';

        $filters = [
            AllowedFilter::partial('item'),
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
            ->allowedSorts('spent_on', 'price', 'item')
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

    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('view', $expense);

        return new ExpenseResource($expense->load('category'));
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        // Created through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $expense = DB::transaction(
            fn () => $request->user()->expenses()->create($request->expenseAttributes())
        );

        return (new ExpenseResource($expense->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        Gate::authorize('update', $expense);

        DB::transaction(fn () => $expense->update($request->expenseAttributes()));

        return new ExpenseResource($expense->load('category'));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        Gate::authorize('delete', $expense);

        DB::transaction(fn () => $expense->delete());

        return response()->json([], 204);
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', self::PER_PAGE);

        // Clamped so a client cannot ask for the whole table in one call.
        return max(1, min($requested, self::MAX_PER_PAGE));
    }
}
