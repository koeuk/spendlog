<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecurringKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringRuleRequest;
use App\Http\Resources\RecurringRuleResource;
use App\Models\RecurringRule;
use App\Services\RecurringRunner;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Recurring
 *
 * Expenses and income that repeat. A rule is a template; the rows it writes
 * are ordinary expenses and incomes, badged `recurring`, and every list,
 * report and budget sees them as such. Every listing is scoped to the
 * caller's own rules — the row kind's manage_all only lets an admin reach a
 * single rule by UUID.
 *
 * @authenticated
 */
class RecurringController extends Controller
{
    public function __construct(
        private readonly RecurringRunner $runner,
    ) {}

    /**
     * List recurring rules
     *
     * Active rules first, then by next occurrence. Only the kinds the caller
     * may view are listed: an account without incomes.view sees its expense
     * rules and nothing else.
     *
     * @queryParam kind string expense or income. Example: expense
     *
     * @response 200 {"data": [{"uuid": "0198f...", "kind": "expense", "title": "Rent", "amount": "450.00", "category": {"uuid": "0198a...", "name": "Housing", "color": "amber", "icon": "home"}, "frequency": "monthly", "starts_on": "2026-09-01", "ends_on": null, "next_run_on": "2026-10-01", "last_run_on": "2026-09-01", "active": true, "note": null, "created_at": "2026-09-01T10:00:00+00:00", "updated_at": "2026-09-01T10:00:00+00:00"}]}
     * @response 403 scenario="token lacks recurring:read" {"message": "Invalid ability provided."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // The ability is a property of the token; the policy is a property of the
        // user. A permission revoked after a token was issued has to still bite.
        Gate::authorize('viewAny', RecurringRule::class);

        $request->validate(['kind' => ['nullable', Rule::enum(RecurringKind::class)]]);

        $user = $request->user();

        // The kinds this person may see, narrowed further by ?kind.
        $kinds = collect(RecurringKind::cases())
            ->filter(fn (RecurringKind $kind) => $user->hasPermissionTo($kind->view()->value))
            ->when($request->filled('kind'), fn ($kinds) => $kinds->filter(
                fn (RecurringKind $kind) => $kind->value === $request->query('kind')
            ))
            ->map(fn (RecurringKind $kind) => $kind->value)
            ->values();

        $rules = RecurringRule::query()
            ->with('category')
            ->forUser($user->id)
            ->whereIn('kind', $kinds)
            ->orderByDesc('active')
            ->orderBy('next_run_on')
            ->orderBy('id')
            ->get();

        return RecurringRuleResource::collection($rules);
    }

    /**
     * Get a recurring rule
     *
     * @urlParam rule string required The rule UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 200 {"data": {"uuid": "0198f...", "kind": "income", "title": "Salary", "amount": "1200.00", "category": null, "frequency": "monthly", "starts_on": "2026-09-01", "ends_on": null, "next_run_on": "2026-10-01", "last_run_on": "2026-09-01", "active": true, "note": null}}
     * @response 403 scenario="someone else's rule" {"message": "This action is unauthorized."}
     * @response 404 scenario="unknown or non-UUID" {"message": "Not found."}
     */
    public function show(RecurringRule $rule): RecurringRuleResource
    {
        Gate::authorize('view', $rule);

        return new RecurringRuleResource($rule->load('category'));
    }

    /**
     * Create a recurring rule
     *
     * The rule is run as soon as it is saved, so one starting today has
     * today's row at once, and one starting in the past has every row it
     * missed. The owner always comes from the token.
     *
     * @bodyParam kind string required expense or income. Example: expense
     * @bodyParam title string required The expense item or income source. Example: Rent
     * @bodyParam amount number required Min 0.01, max 99999999.99. Example: 450
     * @bodyParam currency string USD (default) or KHR. A riel amount is converted and stored in USD. Example: USD
     * @bodyParam category_uuid string Required for an expense rule, forbidden for an income one. Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     * @bodyParam frequency string required daily, weekly, monthly or yearly. Example: monthly
     * @bodyParam starts_on date required Not more than a year ago. Example: 2026-09-01
     * @bodyParam ends_on date After starts_on. Example: 2027-08-31
     * @bodyParam active boolean Default true. Example: true
     * @bodyParam note string Up to 500 characters. Example: Paid to the landlord
     *
     * @response 201 {"data": {"uuid": "0198f...", "kind": "expense", "title": "Rent", "amount": "450.00", "category": {"uuid": "0198a...", "name": "Housing", "color": "amber", "icon": "home"}, "frequency": "monthly", "starts_on": "2026-09-01", "ends_on": null, "next_run_on": "2026-10-01", "last_run_on": "2026-09-01", "active": true, "note": null}}
     * @response 403 scenario="token lacks recurring:write" {"message": "Invalid ability provided."}
     * @response 422 scenario="income rule with a category" {"message": "An income rule has no category.", "errors": {"category_uuid": ["An income rule has no category."]}}
     */
    public function store(RecurringRuleRequest $request): JsonResponse
    {
        Gate::authorize('create', [RecurringRule::class, $request->kind()]);

        // Created through the relationship so user_id comes from the token's
        // owner and is never mass-assignable from the payload. The cursor
        // starts on the first occurrence; the run right after writes every
        // one already due.
        $rule = DB::transaction(function () use ($request) {
            $attributes = $request->ruleAttributes();

            $rule = $request->user()->recurringRules()->create([
                ...$attributes,
                'next_run_on' => $attributes['starts_on'],
            ]);

            $this->runner->run($rule);

            return $rule;
        });

        return (new RecurringRuleResource($rule->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a recurring rule
     *
     * Changes apply to future occurrences only; rows already written are
     * untouched. A new start date or frequency moves the next occurrence to
     * the first one on or after today — the past is never re-created.
     * `kind` cannot change.
     *
     * @urlParam rule string required The rule UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @bodyParam title string required Example: Rent
     * @bodyParam amount number required Example: 475
     * @bodyParam currency string USD (default) or KHR. Example: USD
     * @bodyParam category_uuid string Required for an expense rule, forbidden for an income one. Example: 0198a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b
     * @bodyParam frequency string required Example: monthly
     * @bodyParam starts_on date required Example: 2026-09-01
     * @bodyParam ends_on date Example: 2027-08-31
     * @bodyParam active boolean Example: false
     * @bodyParam note string Example: Raised in September
     *
     * @response 200 {"data": {"uuid": "0198f...", "kind": "expense", "title": "Rent", "amount": "475.00", "category": {"uuid": "0198a...", "name": "Housing"}, "frequency": "monthly", "starts_on": "2026-09-01", "ends_on": null, "next_run_on": "2026-10-01", "last_run_on": "2026-09-01", "active": true, "note": "Raised in September"}}
     * @response 403 scenario="someone else's rule" {"message": "This action is unauthorized."}
     * @response 422 scenario="kind changed" {"message": "The kind of a rule cannot be changed.", "errors": {"kind": ["The kind of a rule cannot be changed."]}}
     */
    public function update(RecurringRuleRequest $request, RecurringRule $rule): RecurringRuleResource
    {
        Gate::authorize('update', $rule);

        DB::transaction(function () use ($request, $rule) {
            $rule->fill($request->ruleAttributes());

            // A different schedule means a different next date. Forward only:
            // the first occurrence from today, so a start moved into the past
            // does not write rows for days that already had their chance.
            if ($rule->isDirty(['starts_on', 'frequency'])) {
                $rule->next_run_on = $rule->firstOccurrenceOnOrAfter(CarbonImmutable::today());
            }

            $rule->save();

            $this->runner->run($rule);
        });

        return new RecurringRuleResource($rule->load('category'));
    }

    /**
     * Delete a recurring rule
     *
     * The rows the rule already wrote stay; they simply stop being badged.
     *
     * @urlParam rule string required The rule UUID. Example: 0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a
     *
     * @response 204 scenario="deleted" {}
     * @response 403 scenario="someone else's rule" {"message": "This action is unauthorized."}
     */
    public function destroy(RecurringRule $rule): JsonResponse
    {
        Gate::authorize('delete', $rule);

        DB::transaction(fn () => $rule->delete());

        return response()->json([], 204);
    }
}
