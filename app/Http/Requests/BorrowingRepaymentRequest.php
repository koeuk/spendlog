<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Http\Requests\Concerns\ConvertsEnteredCurrency;
use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Money paid back against one borrowing.
 *
 * Whether the amount is *affordable* — no more than is still owed — is not
 * validated here: that needs the ledger total under a row lock, which is the
 * controller's job. Same split as SavingsEntryRequest.
 */
class BorrowingRepaymentRequest extends FormRequest
{
    use ConvertsEnteredCurrency;

    /**
     * Authorization is handled by BorrowingPolicy via the controller: a
     * repayment is an update of its borrowing.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $borrowing = $this->route('borrowing');

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'paid_on' => [
                'required',
                'date',
                'before_or_equal:today',
                // Nothing can be paid back before it was borrowed.
                ...($borrowing instanceof Borrowing
                    ? ['after_or_equal:'.$borrowing->borrowed_on->toDateString()]
                    : []),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_on.before_or_equal' => __('You cannot log a repayment in the future.'),
            'paid_on.after_or_equal' => __('The repayment cannot be dated before the money was borrowed.'),
        ];
    }

    /** The entered amount in USD. */

    /**
     * The validated input as the ledger stores it.
     *
     * @return array{amount: float, paid_on: string, note: string|null}
     */
    public function repaymentAttributes(): array
    {
        return [
            'amount' => $this->usdAmount(),
            'paid_on' => $this->validated('paid_on'),
            'note' => $this->validated('note') ?? null,
        ];
    }
}
