<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\LenderType;
use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BorrowingRequest extends FormRequest
{
    /**
     * Authorization is handled by BorrowingPolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lender' => ['required', 'string', 'max:255'],
            'lender_type' => ['required', Rule::enum(LenderType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // What the *entered* amount is denominated in. Absent means USD, so
            // any client that never sends it keeps working unchanged.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'borrowed_on' => ['required', 'date', 'before_or_equal:today'],
            // A due date may well be in the future — that is what one is for —
            // but not before the money changed hands.
            'due_on' => ['nullable', 'date', 'after_or_equal:borrowed_on'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'borrowed_on.before_or_equal' => __('You cannot log a borrowing in the future.'),
            'due_on.after_or_equal' => __('The due date cannot be before the day it was borrowed.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'lender' => __('lender'),
            'lender_type' => __('lender type'),
            'borrowed_on' => __('date'),
            'due_on' => __('due date'),
        ];
    }

    /**
     * The validated input as the model stores it.
     *
     * @return array<string, mixed>
     */
    public function borrowingAttributes(): array
    {
        $data = $this->validated();

        // Every stored amount is USD — see App\Enums\Currency. The currency is
        // a property of what was typed, not of the borrowing, so it is
        // consumed here rather than persisted. Same as IncomeRequest.
        $currency = Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;
        $data['amount'] = $currency->toUsd((float) $data['amount'], AppSetting::current()->khrPerUsd());
        unset($data['currency']);

        $data['lender'] = trim($data['lender']);

        // 'nullable' leaves an omitted field absent rather than null; make
        // both explicit so an update can clear them.
        $data['due_on'] = $data['due_on'] ?? null;
        $data['note'] = $data['note'] ?? null;

        return $data;
    }
}
