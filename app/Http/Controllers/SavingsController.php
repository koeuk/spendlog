<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavingsEntryRequest;
use App\Http\Requests\SavingsPlanRequest;
use App\Models\SavingsEntry;
use App\Models\SavingsPlan;
use App\Models\User;
use App\Services\SavingsSummary;
use App\Support\CalendarOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Savings page — the web counterpart to Api\V1\SavingsController.
 *
 * One month at a time, like Budgets: the month's plan, what actually went
 * aside against it, and the ledger behind that. Every figure comes from the
 * same SavingsSummary the API uses, so the two clients cannot disagree.
 */
class SavingsController extends Controller
{
    public function __construct(private readonly SavingsSummary $summary) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SavingsPlan::class);

        $user = $request->user();
        $month = CalendarOptions::resolveMonth($request->query('month'));

        $entries = SavingsEntry::query()
            ->forUser($user->id)
            ->inMonth($month->toDateString())
            ->orderByDesc('saved_on')
            ->orderByDesc('id')
            ->get();

        $plan = $this->summary->planFor($user, $month);

        return Inertia::render('Savings/Index', [
            'summary' => $this->summary->forMonth($user, $month),
            'plan' => $plan ? [
                'uuid' => $plan->uuid,
                'amount' => (float) $plan->amount,
            ] : null,
            'entries' => $entries->map(fn (SavingsEntry $entry) => [
                'uuid' => $entry->uuid,
                // The ledger is signed; the page speaks in deposit/withdraw
                // with an absolute amount, like the API resource.
                'type' => $entry->isWithdrawal() ? SavingsEntryRequest::WITHDRAW : SavingsEntryRequest::DEPOSIT,
                'amount' => abs((float) $entry->amount),
                'saved_on' => $entry->saved_on->toDateString(),
                'note' => $entry->note,
            ])->all(),
            'month' => $month->format('Y-m'),
            'prev_month' => $month->subMonth()->format('Y-m'),
            'next_month' => $month->addMonth()->format('Y-m'),
            // Month names come from the server so they follow the app locale.
            'months' => CalendarOptions::months(),
            'years' => CalendarOptions::years($user, $month),
            'can' => [
                'create' => $user->can('create', SavingsPlan::class),
                'createEntry' => $user->can('create', SavingsEntry::class),
            ],
        ]);
    }

    /**
     * Set or clear the month's plan — one endpoint, because the page always
     * knows the month and does not care whether a row exists yet.
     */
    public function storePlan(SavingsPlanRequest $request): RedirectResponse
    {
        // SavingsPlanRequest::authorize() only settles that there is no
        // cross-user target here, which is a different question from whether
        // this user may write plans at all.
        Gate::authorize('create', SavingsPlan::class);

        $attributes = $request->planAttributes();

        try {
            DB::transaction(fn () => $request->user()->savingsPlans()->updateOrCreate(
                ['month' => $attributes['month']],
                ['amount' => $attributes['amount']],
            ));
        } catch (\Throwable $e) {
            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()->back()->withSuccess(__('Savings plan saved successfully.'));
    }

    public function destroyPlan(SavingsPlan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        try {
            DB::transaction(fn () => $plan->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        return redirect()->back()->withSuccess(__('Savings plan removed successfully.'));
    }

    /**
     * The deposit / withdrawal screen. Which way the money goes is preselected
     * from ?type=, so the two buttons on the page open the right form.
     */
    public function createEntry(Request $request): Response
    {
        Gate::authorize('create', SavingsEntry::class);

        return Inertia::render('Savings/EntryForm', [
            'total_saved' => $this->summary->totalSaved($request->user()),
            'type' => $request->query('type') === SavingsEntryRequest::WITHDRAW
                ? SavingsEntryRequest::WITHDRAW
                : SavingsEntryRequest::DEPOSIT,
        ]);
    }

    public function editEntry(Request $request, SavingsEntry $entry): Response
    {
        Gate::authorize('update', $entry);

        return Inertia::render('Savings/EntryForm', [
            'total_saved' => $this->summary->totalSaved($request->user()),
            'type' => $entry->isWithdrawal() ? SavingsEntryRequest::WITHDRAW : SavingsEntryRequest::DEPOSIT,
            'entry' => [
                'uuid' => $entry->uuid,
                'amount' => abs((float) $entry->amount),
                'saved_on' => $entry->saved_on->toDateString(),
                'note' => $entry->note,
            ],
        ]);
    }

    public function storeEntry(SavingsEntryRequest $request): RedirectResponse
    {
        Gate::authorize('create', SavingsEntry::class);

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $user) {
                $this->guardWithdrawal($request, $user);

                $user->savingsEntries()->create($request->entryAttributes());
            });
        } catch (ValidationException $e) {
            // Inertia turns this into the field error under the amount box.
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('savings.index', ['month' => substr($request->entryAttributes()['saved_on'], 0, 7)])
            ->withSuccess($request->isWithdrawal()
                ? __('Withdrawal recorded successfully.')
                : __('Deposit recorded successfully.'));
    }

    public function updateEntry(SavingsEntryRequest $request, SavingsEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $entry);

        try {
            DB::transaction(function () use ($request, $entry) {
                // The row being edited is not part of its own ceiling: it is
                // about to be replaced, so what it currently contributes comes
                // off the balance first.
                $this->guardWithdrawal($request, $entry->user, (float) $entry->amount);

                $entry->update($request->entryAttributes());
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('savings.index', ['month' => substr($request->entryAttributes()['saved_on'], 0, 7)])
            ->withSuccess(__('Entry updated successfully.'));
    }

    public function destroyEntry(SavingsEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        try {
            DB::transaction(fn () => $entry->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        return redirect()->back()->withSuccess(__('Entry deleted successfully.'));
    }

    /**
     * Refuse a withdrawal larger than the all-time balance, under a row-level
     * lock on the account so two racing withdrawals cannot both read the same
     * figure. Mirrors Api\V1\SavingsController.
     */
    private function guardWithdrawal(SavingsEntryRequest $request, User $user, float $excluding = 0.0): void
    {
        if (! $request->isWithdrawal()) {
            return;
        }

        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        if (! $this->summary->canWithdraw($locked, $request->usdAmount() + $excluding)) {
            throw ValidationException::withMessages([
                'amount' => __('You cannot withdraw more than is saved.'),
            ]);
        }
    }
}
