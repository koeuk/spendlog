<?php

namespace App\Http\Controllers;

use App\Enums\CategoryColor;
use App\Http\Requests\SavingsEntryRequest;
use App\Http\Requests\SavingsGoalRequest;
use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use App\Services\SavingsSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Savings pages — the web counterpart to Api\V1\SavingsController.
 *
 * A goal's balance is always the sum of its ledger, and every figure on these
 * pages comes from the same SavingsSummary the API uses, so the two clients
 * cannot disagree about how far along a goal is.
 */
class SavingsController extends Controller
{
    /** How much of a goal's ledger the detail page shows. */
    private const ENTRY_LIMIT = 100;

    public function __construct(private readonly SavingsSummary $summary) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SavingsGoal::class);

        $goals = SavingsGoal::query()
            ->forUser($request->user()->id)
            ->withSaved()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Savings/Index', [
            'goals' => $goals->map(fn (SavingsGoal $goal) => $this->goalRow($goal))->all(),
            'totals' => $this->summary->forMonth($request->user(), CarbonImmutable::now()),
            'can' => [
                'create' => $request->user()->can('create', SavingsGoal::class),
            ],
        ]);
    }

    public function show(Request $request, SavingsGoal $goal): Response
    {
        Gate::authorize('view', $goal);

        $goal->loadSum('entries as saved_total', 'amount');

        $entries = $goal->entries()
            ->orderByDesc('saved_on')
            ->orderByDesc('id')
            ->limit(self::ENTRY_LIMIT)
            ->get();

        return Inertia::render('Savings/Show', [
            'goal' => $this->goalRow($goal),
            'entries' => $entries->map(fn (SavingsEntry $entry) => [
                'uuid' => $entry->uuid,
                // The ledger is signed; the page speaks in deposit/withdraw
                // with an absolute amount, like the API resource.
                'type' => $entry->isWithdrawal() ? SavingsEntryRequest::WITHDRAW : SavingsEntryRequest::DEPOSIT,
                'amount' => abs((float) $entry->amount),
                'saved_on' => $entry->saved_on->toDateString(),
                'note' => $entry->note,
            ])->all(),
            // Deposits, withdrawals and deleting a line all change the balance,
            // so they hang off update — see SavingsGoalPolicy.
            'can' => [
                'update' => $request->user()->can('update', $goal),
                'delete' => $request->user()->can('delete', $goal),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', SavingsGoal::class);

        return Inertia::render('Savings/Form', [
            'colors' => $this->colorOptions(),
        ]);
    }

    public function edit(SavingsGoal $goal): Response
    {
        Gate::authorize('update', $goal);

        return Inertia::render('Savings/Form', [
            'goal' => [
                'uuid' => $goal->uuid,
                'name' => $goal->name,
                'target_amount' => (float) $goal->target_amount,
                'deadline' => $goal->deadline?->toDateString(),
                'color' => $goal->color?->value,
            ],
            'colors' => $this->colorOptions(),
        ]);
    }

    public function store(SavingsGoalRequest $request): RedirectResponse
    {
        Gate::authorize('create', SavingsGoal::class);

        try {
            // Created through the relationship so user_id is never mass-assignable.
            $goal = DB::transaction(fn () => $request->user()->savingsGoals()->create($request->goalAttributes()));
        } catch (\Throwable $e) {
            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('savings.show', $goal)
            ->withSuccess(__('Savings goal added successfully.'));
    }

    public function update(SavingsGoalRequest $request, SavingsGoal $goal): RedirectResponse
    {
        Gate::authorize('update', $goal);

        try {
            DB::transaction(fn () => $goal->update($request->goalAttributes()));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('savings.show', $goal)
            ->withSuccess(__('Savings goal updated successfully.'));
    }

    public function destroy(SavingsGoal $goal): RedirectResponse
    {
        Gate::authorize('delete', $goal);

        try {
            // The ledger goes with it — the FK is cascadeOnDelete.
            DB::transaction(fn () => $goal->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        return redirect()
            ->route('savings.index')
            ->withSuccess(__('Savings goal deleted successfully.'));
    }

    /**
     * The deposit / withdrawal screen. Which way the money goes is preselected
     * from ?type=, so the two buttons on the goal page open the right form.
     */
    public function createEntry(Request $request, SavingsGoal $goal): Response
    {
        Gate::authorize('update', $goal);

        $goal->loadSum('entries as saved_total', 'amount');

        return Inertia::render('Savings/EntryForm', [
            'goal' => $this->goalRow($goal),
            'type' => $request->query('type') === SavingsEntryRequest::WITHDRAW
                ? SavingsEntryRequest::WITHDRAW
                : SavingsEntryRequest::DEPOSIT,
        ]);
    }

    public function storeEntry(SavingsEntryRequest $request, SavingsGoal $goal): RedirectResponse
    {
        // A deposit or withdrawal changes the goal's balance, so it is an
        // update of the goal — there is no separate entry policy.
        Gate::authorize('update', $goal);

        try {
            DB::transaction(function () use ($request, $goal) {
                // Locked so two withdrawals racing each other cannot both read
                // the same balance and together take out more than was there.
                $locked = SavingsGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();

                if ($request->isWithdrawal()
                    && ! $this->summary->canWithdraw($this->summary->saved($locked), $request->usdAmount())) {
                    throw ValidationException::withMessages([
                        'amount' => __('You cannot withdraw more than is saved.'),
                    ]);
                }

                $entry = $goal->entries()->make($request->entryAttributes());
                // The goal's owner, not the caller: an admin depositing on
                // someone's behalf records it under the account it belongs to.
                $entry->user()->associate($goal->user_id);
                $entry->save();
            });
        } catch (ValidationException $e) {
            // Inertia turns this into the field error under the amount box.
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('savings.show', $goal)
            ->withSuccess($request->isWithdrawal()
                ? __('Withdrawal recorded successfully.')
                : __('Deposit recorded successfully.'));
    }

    /**
     * The entry must belong to the goal in the URL — the route is
     * scopeBindings(), so one from another goal is a 404, not a 403.
     */
    public function destroyEntry(SavingsGoal $goal, SavingsEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $goal);

        try {
            DB::transaction(fn () => $entry->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        return redirect()->back()->withSuccess(__('Entry deleted successfully.'));
    }

    /**
     * One goal as the pages render it: the row plus every figure the summary
     * derives from its balance.
     *
     * Expects the goal to carry saved_total (withSaved() / loadSum()) so a
     * list pays one query rather than one per card.
     *
     * @return array<string, mixed>
     */
    private function goalRow(SavingsGoal $goal): array
    {
        $saved = $this->summary->saved($goal);
        $target = (float) $goal->target_amount;

        return [
            'uuid' => $goal->uuid,
            'name' => $goal->name,
            'target_amount' => $target,
            'saved' => $saved,
            'remaining' => $this->summary->remaining($saved, $target),
            'percent' => $this->summary->percent($saved, $target),
            'reached' => $this->summary->reached($saved, $target),
            'deadline' => $goal->deadline?->toDateString(),
            'color' => $goal->color?->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function colorOptions(): array
    {
        return array_map(fn (CategoryColor $color) => $color->value, CategoryColor::cases());
    }
}
