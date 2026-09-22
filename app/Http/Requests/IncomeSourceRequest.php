<?php

namespace App\Http\Requests;

use App\Models\IncomeSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A name in the account's source catalogue.
 *
 * Unique per account, not globally: two people may both be paid a salary.
 */
class IncomeSourceRequest extends FormRequest
{
    /**
     * Authorization is handled by IncomeSourcePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $source = $this->route('source');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('income_sources', 'name')
                    ->where('user_id', $this->user()->id)
                    // An edit that changes only the capitalisation must not
                    // collide with the row it is editing.
                    ->ignore($source instanceof IncomeSource ? $source->id : null),
            ],
            /*
             * Whether renaming here also rewrites the income already filed
             * under the old name.
             *
             * Off by default: the catalogue is suggestions, and rewriting
             * history is the larger act of the two. The client asks.
             */
            'rewrite_incomes' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => __('You already have a source with that name.'),
        ];
    }

    public function sourceName(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function rewritesIncomes(): bool
    {
        return $this->boolean('rewrite_incomes');
    }
}
