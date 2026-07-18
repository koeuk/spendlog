<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\AppSetting;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetRequest extends FormRequest
{
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
            'amount' => $this->enteredCurrency()
                ->toUsd((float) $data['amount'], AppSetting::current()->khrPerUsd()),
        ];
    }

    /**
     * What the submitted amount is denominated in.
     *
     * Read straight off the raw input rather than the validated data, because
     * rules() needs it to build the amount rule and validation has not run yet.
     * An absent or unrecognised value means USD, so callers predating the
     * currency toggle keep working.
     */
    private function enteredCurrency(): Currency
    {
        return Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;
    }

    /**
     * The uuid passed `exists` a moment ago, but that was a separate query — the
     * row can be gone by the time we look it up.
     *
     * Null is not a neutral failure here: it is this schema's encoding for the
     * overall budget covering every category (see the rule above, BudgetSummary,
     * and the category_key generated column). Letting a vanished category fall
     * through to null would quietly rewrite "$250 for Food" as "$250 across
     * everything" — no exception, no error, and a wrong number on the dashboard.
     */
    private function resolveCategoryId(string $categoryUuid): int
    {
        $id = Category::where('uuid', $categoryUuid)->value('id');

        if ($id === null) {
            throw ValidationException::withMessages([
                'category_uuid' => __('That category no longer exists.'),
            ]);
        }

        return $id;
    }
}
