<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    /**
     * Authorization is handled by ExpensePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'category_uuid' => ['required', 'uuid', 'exists:categories,uuid'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'spent_on.before_or_equal' => 'You cannot log an expense in the future.',
            'category_uuid.required' => 'Please pick a category.',
            'category_uuid.exists' => 'That category no longer exists.',
        ];
    }

    /**
     * The frontend only ever sees UUIDs, so swap the category UUID for the
     * internal foreign key before the model is written.
     *
     * @return array<string, mixed>
     */
    public function expenseAttributes(): array
    {
        $data = $this->validated();

        $data['category_id'] = Category::where('uuid', $data['category_uuid'])->value('id');
        unset($data['category_uuid']);

        return $data;
    }
}
