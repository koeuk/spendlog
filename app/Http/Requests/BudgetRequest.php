<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

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
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
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

        return [
            'category_id' => $data['category_uuid']
                ? Category::where('uuid', $data['category_uuid'])->value('id')
                : null,
            'month' => $data['month'].'-01',
            'amount' => $data['amount'],
        ];
    }
}
