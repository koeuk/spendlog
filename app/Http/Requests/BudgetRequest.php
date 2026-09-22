<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Http\Requests\Concerns\ConvertsEnteredCurrency;
use App\Http\Requests\Concerns\ResolvesCategoryUuid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    use ConvertsEnteredCurrency, ResolvesCategoryUuid;

    /**
     * Budgets are always written against the authenticated user's own
     * relationship, so there is no cross-user target to authorize.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Null means the overall budget covering every category.
            'category_uuid' => ['nullable', 'uuid', 'exists:categories,uuid'],
            'month' => ['required', 'date_format:Y-m'],
            // The floor is per-currency: ៛99 is not a payable amount, $0 is a
            // real budget. See Currency::minimumInput.
            'amount' => ['required', 'numeric', 'min:'.$this->enteredCurrency()->minimumInput(), 'max:99999999.99'],
            // What the *entered* amount is denominated in. Absent means USD, so
            // existing callers keep working. See App\Enums\Currency.
            'currency' => ['nullable', Rule::enum(Currency::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => 'The month must look like 2026-07.',
        ];
    }

    /**
     * Resolve the public UUID to the internal foreign key, and normalise the
     * month to the first day so every row for a month collides on the
     * (user, category, month) unique index.
     *
     * @return array{category_id: int|null, month: string, amount: string}
     */
    public function budgetAttributes(): array
    {
        $data = $this->validated();

        // Coalesce: an overall budget omits category_uuid entirely, so
        // 'nullable' leaves the key absent rather than null.
        $categoryUuid = $data['category_uuid'] ?? null;

        return [
            'category_id' => $categoryUuid ? $this->resolveCategoryId($categoryUuid) : null,
            'month' => $data['month'].'-01',
            // Budgets are compared against stored expense prices, which are
            // always USD — a riel budget left unconverted would read as ~4100x
            // its real size and never report as over. Same as ExpenseRequest.
            'amount' => $this->usdAmount(),
        ];
    }
}
