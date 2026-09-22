<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringKind;
use App\Http\Requests\Concerns\ConvertsEnteredCurrency;
use App\Http\Requests\Concerns\ResolvesCategoryUuid;
use App\Models\RecurringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringRuleRequest extends FormRequest
{
    use ConvertsEnteredCurrency, ResolvesCategoryUuid;

    /**
     * Authorization is handled by RecurringRulePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existing = $this->existingRule();
        $kind = $this->kind();

        return [
            // Fixed for the life of a rule: switching an expense rule to income
            // would orphan its category and change which permissions guard it.
            // An update may echo the current kind back, as the full-shape
            // PATCHes elsewhere in this API do — just never a different one.
            'kind' => $existing === null
                ? ['required', Rule::enum(RecurringKind::class)]
                : ['sometimes', Rule::in([$existing->kind->value])],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // What the *entered* amount is denominated in. Absent means USD, so
            // any client that never sends it keeps working unchanged.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            // An expense needs its category; an income has none to give.
            'category_uuid' => [
                Rule::requiredIf($kind === RecurringKind::Expense),
                Rule::prohibitedIf($kind === RecurringKind::Income),
                'nullable',
                'uuid',
                'exists:categories,uuid',
            ],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'starts_on' => [
                'required',
                'date',
                // A new rule may start in the past — catch-up writes the missed
                // rows — but not so far back that it rewrites a year of history
                // in one request. An update never re-creates the past, so it
                // is free to keep whatever start it already has.
                ...($existing === null ? ['after_or_equal:'.today()->subYear()->toDateString()] : []),
            ],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'active' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.in' => __('The kind of a rule cannot be changed.'),
            'category_uuid.required' => __('Please pick a category.'),
            'category_uuid.prohibited' => __('An income rule has no category.'),
            'category_uuid.exists' => __('That category no longer exists.'),
            'starts_on.after_or_equal' => __('A rule cannot start more than a year ago.'),
            'ends_on.after' => __('The end date must be after the start date.'),
        ];
    }

    /**
     * The validated input as the model stores it.
     *
     * The frontend only ever sees UUIDs, so the category UUID is swapped for
     * the internal foreign key; an income rule stores null there.
     *
     * @return array<string, mixed>
     */
    public function ruleAttributes(): array
    {
        $data = $this->validated();

        $data['category_id'] = $this->kind() === RecurringKind::Expense
            ? $this->resolveCategoryId($this->validated('category_uuid'))
            : null;
        unset($data['category_uuid']);

        $data['amount'] = $this->usdAmount();
        unset($data['currency']);

        // 'nullable' leaves an omitted value absent rather than null; make
        // both explicit so an update can clear them.
        $data['ends_on'] = $data['ends_on'] ?? null;
        $data['note'] = $data['note'] ?? null;

        if ($this->existingRule() === null) {
            // A new rule is on unless told otherwise.
            $data['active'] = $data['active'] ?? true;
        } else {
            // An existing one keeps its switch where it is when the field is
            // left out, and its kind is immutable — so an update writes neither.
            unset($data['kind']);
        }

        return $data;
    }

    /** The kind this request is about: the rule's own on update, the input on create. */
    public function kind(): ?RecurringKind
    {
        return $this->existingRule()?->kind ?? RecurringKind::tryFrom((string) $this->input('kind'));
    }

    /** The bound rule on an update; null on create. */
    private function existingRule(): ?RecurringRule
    {
        $rule = $this->route('rule');

        return $rule instanceof RecurringRule ? $rule : null;
    }
}
