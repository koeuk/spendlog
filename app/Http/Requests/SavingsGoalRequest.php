<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\Currency;
use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavingsGoalRequest extends FormRequest
{
    /**
     * Authorization is handled by SavingsGoalPolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // What the *entered* target is denominated in. Absent means USD.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            // A new goal cannot already be overdue. An existing one can: the
            // date passing is exactly the state the goal is meant to be able
            // to sit in while its owner edits the name or the target.
            'deadline' => array_filter([
                'nullable',
                'date',
                $this->isMethod('POST') ? 'after_or_equal:today' : null,
            ]),
            'color' => ['nullable', Rule::enum(CategoryColor::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'deadline.after_or_equal' => __('The deadline cannot be in the past.'),
        ];
    }

    /**
     * The validated input as the model stores it.
     *
     * @return array<string, mixed>
     */
    public function goalAttributes(): array
    {
        $data = $this->validated();

        // Stored in USD like every amount — a riel target left unconverted
        // would read as ~4100x its real size and never be reached.
        $currency = Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;
        $data['target_amount'] = $currency->toUsd((float) $data['target_amount'], AppSetting::current()->khrPerUsd());
        unset($data['currency']);

        $data['deadline'] = $data['deadline'] ?? null;

        // No colour on create means the palette's first case; no colour on an
        // edit means "leave it alone" — there is no cleared state to store.
        if (blank($data['color'] ?? null)) {
            unset($data['color']);

            if ($this->isMethod('POST')) {
                $data['color'] = CategoryColor::cases()[0];
            }
        }

        return $data;
    }
}
