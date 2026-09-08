<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{
    /**
     * Authorization is handled by IncomePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // What the *entered* amount is denominated in. Absent means USD, so
            // any client that never sends it keeps working unchanged.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_on.before_or_equal' => __('You cannot log income in the future.'),
        ];
    }

    /**
     * The validated input as the model stores it.
     *
     * @return array<string, mixed>
     */
    public function incomeAttributes(): array
    {
        $data = $this->validated();

        // Every stored amount is USD — see App\Enums\Currency. The currency is
        // a property of what was typed, not of the income, so it is consumed
        // here rather than persisted. Same as ExpenseRequest.
        $currency = Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;
        $data['amount'] = $currency->toUsd((float) $data['amount'], AppSetting::current()->khrPerUsd());
        unset($data['currency']);

        // 'nullable' leaves an omitted note absent rather than null; make it
        // explicit so an update can clear one.
        $data['note'] = $data['note'] ?? null;

        return $data;
    }
}
