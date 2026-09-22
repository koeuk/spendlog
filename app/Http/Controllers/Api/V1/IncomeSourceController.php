<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomeSourceRequest;
use App\Http\Resources\IncomeSourceResource;
use App\Models\Income;
use App\Models\IncomeSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Income sources
 *
 * The names an account files its income under — "Salary", "Freelance",
 * "Gift" — and what the income form and the savings deposit form offer.
 *
 * A catalogue of **suggestions**. `incomes.source` stays free text, so a name
 * can be renamed or removed here without rewriting, or losing, the income that
 * used it; a rename says whether to carry the old income across. Logging
 * income under a name the catalogue has not heard of quietly adds it, so
 * typing keeps working and the list is something to tidy rather than to fill
 * in first.
 *
 * @authenticated
 */
class IncomeSourceController extends Controller
{
    /**
     * List sources
     *
     * Every name in the catalogue with how much income is filed under it,
     * busiest first. `uses` counts on the string, so a name nobody has used
     * yet is `0` rather than missing.
     *
     * @response 200 {"data": [{"uuid": "0198a...", "name": "Salary", "uses": 12, "total": "9400.00", "created_at": "2026-09-01T10:00:00+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', IncomeSource::class);

        $user = $request->user();

        // Correlated on the name, not a join: a join would multiply the
        // catalogue row by its income before it could be counted.
        $matching = fn (string $aggregate) => Income::query()
            ->forUser($user->id)
            ->whereColumn('incomes.source', 'income_sources.name')
            ->selectRaw($aggregate);

        $sources = IncomeSource::query()
            ->forUser($user->id)
            ->select('income_sources.*')
            ->selectSub($matching('COUNT(*)'), 'uses')
            ->selectSub($matching('COALESCE(SUM(amount), 0)'), 'total')
            ->orderByDesc('uses')
            ->orderBy('name')
            ->get();

        return IncomeSourceResource::collection($sources);
    }

    /**
     * Add a source
     *
     * @bodyParam name string required Up to 255 characters, unique for this account. Example: Freelance
     *
     * @response 201 {"data": {"uuid": "0198a...", "name": "Freelance", "uses": 0, "total": "0.00"}}
     * @response 422 scenario="already there" {"message": "You already have a source with that name.", "errors": {"name": ["You already have a source with that name."]}}
     */
    public function store(IncomeSourceRequest $request): JsonResponse
    {
        Gate::authorize('create', IncomeSource::class);

        // Written through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload.
        $source = DB::transaction(
            fn () => $request->user()->incomeSources()->create(['name' => $request->sourceName()]),
        );

        return (new IncomeSourceResource($source))->response()->setStatusCode(201);
    }

    /**
     * Rename a source
     *
     * Send `rewrite_incomes` to carry the income filed under the old name
     * across to the new one. Without it the catalogue changes and the history
     * keeps the name it was entered with — which is the right answer when the
     * old name was never a mistake, only no longer the one being offered.
     *
     * Renaming onto a name the account already has is a 422, not a silent
     * merge: two rows becoming one is a thing to ask for, not to discover.
     *
     * @urlParam source string required The source UUID. Example: 0198a1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam name string required Up to 255 characters, unique for this account. Example: Consulting
     * @bodyParam rewrite_incomes boolean Also rename the income filed under the old name. Example: true
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Consulting", "uses": 0, "total": "0.00"}}
     * @response 403 scenario="someone else's source" {"message": "This action is unauthorized."}
     */
    public function update(IncomeSourceRequest $request, IncomeSource $source): IncomeSourceResource
    {
        Gate::authorize('update', $source);

        $name = $request->sourceName();
        $was = $source->name;

        DB::transaction(function () use ($request, $source, $name, $was) {
            $source->update(['name' => $name]);

            if ($request->rewritesIncomes() && $name !== $was) {
                Income::query()
                    ->forUser($source->user_id)
                    ->where('source', $was)
                    ->update(['source' => $name]);
            }
        });

        return new IncomeSourceResource($source);
    }

    /**
     * Remove a source
     *
     * Stops it being offered. The income filed under it is untouched and keeps
     * the name — this is a suggestion going away, not a record.
     *
     * @urlParam source string required The source UUID. Example: 0198a1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's source" {"message": "This action is unauthorized."}
     */
    public function destroy(IncomeSource $source): JsonResponse
    {
        Gate::authorize('delete', $source);

        DB::transaction(fn () => $source->delete());

        return response()->json([], 204);
    }
}
