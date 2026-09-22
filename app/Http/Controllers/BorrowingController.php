<?php

namespace App\Http\Controllers;

use App\Enums\LenderType;
use App\Http\Requests\BorrowingRepaymentRequest;
use App\Http\Requests\BorrowingRequest;
use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use App\Services\BorrowingSummary;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The Borrowing page — the web counterpart to Api\V1\BorrowingController.
 *
 * Own rows only, like Income: there is no Everyone view. An admin holding
 * borrowings.manage_all can still reach a single row by its URL, which is
 * what the policy allows, but nothing on this page lists other people's
 * debts.
 */
class BorrowingController extends Controller
{
    use PaginatesLists;

    /** The status segment: what is still owed, what is paid back, or both. */
    private const STATUSES = ['open', 'settled', 'all'];

    public function __construct(private readonly BorrowingSummary $summary) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Borrowing::class);

        $status = $this->validStatus($request->query('status'));

        $query = QueryBuilder::for(Borrowing::class)
            ->withRepaid()
            ->withCount('repayments')
            // Applied first so no filter can widen the list beyond the
            // owner's rows.
            ->where('user_id', $request->user()->id);

        /*
         * Which list: still owed, paid back, or both. With both, still-owed
         * rows lead — what you owe is what you came to see, and a settled
         * debt is a record rather than a task. That ordering has to go on
         * before the spatie sorts below, which are applied as they are
         * declared; after them it would only break ties.
         */
        if ($status === 'open') {
            $query->open();
        } elseif ($status === 'settled') {
            $query->settled();
        } else {
            $query->orderByRaw('CASE WHEN '.Borrowing::remainingSql().' > 0 THEN 0 ELSE 1 END');
        }

        $borrowings = $query
            ->allowedFilters(
                AllowedFilter::partial('lender'),
                AllowedFilter::exact('type', 'lender_type'),
            )
            ->allowedSorts('borrowed_on', 'amount', 'due_on', 'lender')
            ->defaultSort('-borrowed_on', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Borrowings/Index', [
            'borrowings' => collect($borrowings->items())
                ->map(fn (Borrowing $borrowing) => $this->row($borrowing))
                ->all(),
            'pagination' => $this->paginationMeta($borrowings),
            // Cast for the same reason as ExpenseController@index: an empty
            // only() is a JSON array, and filters.filter then resolves to
            // Array.prototype.filter instead of undefined.
            'filters' => (object) $request->only('filter', 'sort'),
            'status' => $status,
            'summary' => $this->summary->forUser($request->user()),
            'lender_types' => LenderType::options(),
            'can' => [
                'create' => $request->user()->can('create', Borrowing::class),
            ],
        ]);
    }

    public function show(Request $request, Borrowing $borrowing): Response
    {
        Gate::authorize('view', $borrowing);

        $borrowing->load(['repayments' => fn ($query) => $query->orderByDesc('paid_on')->orderByDesc('id')]);

        return Inertia::render('Borrowings/Show', [
            'borrowing' => $this->row($borrowing),
            'repayments' => $borrowing->repayments
                ->map(fn (BorrowingRepayment $repayment) => [
                    'uuid' => $repayment->uuid,
                    'amount' => (float) $repayment->amount,
                    'paid_on' => $repayment->paid_on->toDateString(),
                    'note' => $repayment->note,
                ])
                ->all(),
            'can' => [
                'update' => $request->user()->can('update', $borrowing),
                'delete' => $request->user()->can('delete', $borrowing),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Borrowing::class);

        return Inertia::render('Borrowings/Form', [
            'lenders' => $this->lenderOptions($request),
            'lender_types' => LenderType::options(),
        ]);
    }

    public function edit(Request $request, Borrowing $borrowing): Response
    {
        Gate::authorize('update', $borrowing);

        return Inertia::render('Borrowings/Form', [
            'borrowing' => [
                'uuid' => $borrowing->uuid,
                'lender' => $borrowing->lender,
                'lender_type' => $borrowing->lender_type->value,
                'amount' => (float) $borrowing->amount,
                // The floor for the amount: it cannot drop below what has
                // already been paid back against it.
                'repaid' => $borrowing->repaid(),
                'borrowed_on' => $borrowing->borrowed_on->toDateString(),
                'due_on' => $borrowing->due_on?->toDateString(),
                'note' => $borrowing->note,
            ],
            'lenders' => $this->lenderOptions($request),
            'lender_types' => LenderType::options(),
        ]);
    }

    public function store(BorrowingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Borrowing::class);

        try {
            // Created through the relationship so user_id is never mass-assignable.
            DB::transaction(fn () => $request->user()->borrowings()->create($request->borrowingAttributes()));
        } catch (\Throwable $e) {
            // getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        // Not back(): the form is its own page, so back() would land on the
        // form that was just submitted.
        return redirect()
            ->route('borrowings.index')
            ->withSuccess(__('Borrowing added successfully.'));
    }

    public function update(BorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('update', $borrowing);

        $attributes = $request->borrowingAttributes();

        try {
            DB::transaction(function () use ($borrowing, $attributes) {
                $this->guardAmount($borrowing, $attributes['amount']);

                $borrowing->update($attributes);
            });
        } catch (ValidationException $e) {
            // Inertia turns this into the field error under the amount box.
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('borrowings.show', $borrowing)
            ->withSuccess(__('Borrowing updated successfully.'));
    }

    public function destroy(Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('delete', $borrowing);

        try {
            // The ledger goes with it — the foreign key cascades.
            DB::transaction(fn () => $borrowing->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        // The index, not back(): this is offered from the row's own page,
        // which no longer exists.
        return redirect()
            ->route('borrowings.index')
            ->withSuccess(__('Borrowing deleted successfully.'));
    }

    /**
     * The repayment screen. Adding a repayment changes what the borrowing
     * still owes, so it is authorised as an update of the borrowing.
     */
    public function createRepayment(Borrowing $borrowing): Response
    {
        Gate::authorize('update', $borrowing);

        return Inertia::render('Borrowings/RepaymentForm', [
            'borrowing' => [
                'uuid' => $borrowing->uuid,
                'lender' => $borrowing->lender,
                'amount' => (float) $borrowing->amount,
                'remaining' => $borrowing->remaining(),
                'borrowed_on' => $borrowing->borrowed_on->toDateString(),
            ],
        ]);
    }

    public function storeRepayment(BorrowingRepaymentRequest $request, Borrowing $borrowing): RedirectResponse
    {
        Gate::authorize('update', $borrowing);

        try {
            DB::transaction(function () use ($request, $borrowing) {
                $locked = $this->lockedForRepayment($borrowing, $request->usdAmount());

                $repayment = $locked->repayments()->make($request->repaymentAttributes());
                // From the borrowing, never the request: the ledger line
                // belongs to whoever owes the money, even when an admin
                // records it for them.
                $repayment->user()->associate($locked->user_id);
                $repayment->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }

        return redirect()
            ->route('borrowings.show', $borrowing)
            ->withSuccess(__('Repayment recorded successfully.'));
    }

    public function destroyRepayment(Borrowing $borrowing, BorrowingRepayment $repayment): RedirectResponse
    {
        Gate::authorize('update', $borrowing);

        // The route carries both keys; a repayment under a borrowing it does
        // not belong to is not found, not forbidden.
        abort_unless($repayment->borrowing_id === $borrowing->id, 404);

        try {
            DB::transaction(fn () => $repayment->delete());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }

        return redirect()->back()->withSuccess(__('Repayment deleted successfully.'));
    }

    /**
     * Refuse a repayment larger than what is still owed, under a row lock on
     * the borrowing so two racing repayments cannot both read the same
     * figure. Returns the locked row for the write. Mirrors the API.
     */
    private function lockedForRepayment(Borrowing $borrowing, float $amount): Borrowing
    {
        $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();

        if ($amount > $locked->remaining()) {
            throw ValidationException::withMessages([
                'amount' => __('You cannot repay more than is still owed (:amount).', [
                    'amount' => '$'.number_format($locked->remaining(), 2),
                ]),
            ]);
        }

        return $locked;
    }

    /**
     * An edit cannot take the amount below what has already been paid back:
     * the ledger would then say more was returned than was ever owed.
     */
    private function guardAmount(Borrowing $borrowing, float $amount): void
    {
        $repaid = $borrowing->repaid();

        if ($amount < $repaid) {
            throw ValidationException::withMessages([
                'amount' => __('The amount cannot be less than what has already been repaid (:amount).', [
                    'amount' => '$'.number_format($repaid, 2),
                ]),
            ]);
        }
    }

    /** 'open' | 'settled' | 'all'; junk falls back to everything. */
    private function validStatus(mixed $value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : 'all';
    }

    /**
     * The lenders this person has named before, most frequent first, for the
     * picker. Per person, like the rows themselves.
     *
     * @return array<int, string>
     */
    private function lenderOptions(Request $request): array
    {
        return Borrowing::query()
            ->forUser($request->user()->id)
            ->groupBy('lender')
            ->selectRaw('lender, COUNT(*) as uses')
            ->orderByDesc('uses')
            ->orderBy('lender')
            ->pluck('lender')
            ->all();
    }

    /**
     * One borrowing as the pages render it. The money fields are floats for
     * the same reason as every other Inertia page; the API formats strings.
     *
     * @return array<string, mixed>
     */
    private function row(Borrowing $borrowing): array
    {
        return [
            'uuid' => $borrowing->uuid,
            'lender' => $borrowing->lender,
            'lender_type' => $borrowing->lender_type->value,
            'lender_type_label' => $borrowing->lender_type->label(),
            'amount' => (float) $borrowing->amount,
            'repaid' => $borrowing->repaid(),
            'remaining' => $borrowing->remaining(),
            'percent' => $borrowing->percentRepaid(),
            'settled' => $borrowing->isSettled(),
            'overdue' => $borrowing->isOverdue(),
            'borrowed_on' => $borrowing->borrowed_on->toDateString(),
            'due_on' => $borrowing->due_on?->toDateString(),
            'note' => $borrowing->note,
            'repayments_count' => (int) ($borrowing->repayments_count ?? $borrowing->repayments()->count()),
        ];
    }
}
