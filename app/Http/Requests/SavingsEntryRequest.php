<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A deposit into, or withdrawal from, savings.
 *
 * The client always sends a positive amount and says which way it goes; the
 * sign is applied here so the stored ledger is a plain signed column. Whether
 * a withdrawal is *affordable* is not validated here — that needs the
 * all-time balance under a row lock, which is the controller's job.
 */
class SavingsEntryRequest extends FormRequest
{
    public const DEPOSIT = 'deposit';

    public const WITHDRAW = 'withdraw';

    /**
     * Authorization is handled by SavingsEntryPolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([self::DEPOSIT, self::WITHDRAW])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'saved_on' => ['required', 'date', 'before_or_equal:today'],
            // Where a deposit came from. A free label offered from the
            // account's own income sources, not a key into them — see the
            // migration for why.
            'source' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'saved_on.before_or_equal' => __('You cannot log a deposit in the future.'),
        ];
    }

    public function isWithdrawal(): bool
    {
        return $this->validated('type') === self::WITHDRAW;
    }

    /**
     * The entered amount in USD, always positive. The sign is applied by
     * entryAttributes() once the controller has confirmed it is affordable.
     */
    public function usdAmount(): float
    {
        $currency = Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;

        return $currency->toUsd((float) $this->validated('amount'), AppSetting::current()->khrPerUsd());
    }

    /**
     * The validated input as the ledger stores it: signed amount, no type.
     *
     * @return array{amount: float, source: string|null, saved_on: string, note: string|null}
     */
    public function entryAttributes(): array
    {
        $amount = $this->usdAmount();
        $source = trim((string) $this->validated('source'));

        return [
            'amount' => $this->isWithdrawal() ? -$amount : $amount,
            // Money leaving savings has no origin to name, so a source sent
            // with a withdrawal is dropped rather than stored misleadingly.
            'source' => ($this->isWithdrawal() || $source === '') ? null : $source,
            'saved_on' => $this->validated('saved_on'),
            'note' => $this->validated('note') ?? null,
        ];
    }
}
