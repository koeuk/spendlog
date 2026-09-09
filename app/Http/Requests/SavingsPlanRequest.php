<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * How much to put aside in one month.
 *
 * The same shape as BudgetRequest, because a plan is the same kind of thing as
 * a budget: one amount for one month, upserted rather than created and edited.
 */
class SavingsPlanRequest extends FormRequest
{
    /**
     * Plans are always written against the authenticated user's own
     * relationship, so there is no cross-user target to authorize.
     * SavingsPlanPolicy still runs in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            // Zero is a real plan — "nothing aside this month" — so the floor
            // is 0 rather than a cent. Clearing the month is DELETE, not $0.
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // What the *entered* amount is denominated in. Absent means USD, so
            // existing callers keep working. See App\Enums\Currency.
            'currency' => ['nullable', Rule::enum(Currency::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => __('The month must look like 2026-07.'),
        ];
    }

    /**
     * Normalise the month to the first day so every row for a month collides
     * on the (user, month) unique index.
     *
     * @return array{month: string, amount: float}
     */
    public function planAttributes(): array
    {
        $data = $this->validated();

        $currency = Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;

        return [
            'month' => $data['month'].'-01',
            // Plans are compared against stored entry amounts, which are always
            // USD — a riel plan left unconverted would read as ~4100x its real
            // size and never be met. Same as BudgetRequest.
            'amount' => $currency->toUsd((float) $data['amount'], AppSetting::current()->khrPerUsd()),
        ];
    }
}
