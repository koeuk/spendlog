<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The dashboard spending-guidance copy: an enabled flag plus a warning and an
 * advice message, each one plain box — the same shape CategoryRequest validates
 * for a category name.
 */
class SpendingRequest extends FormRequest
{
    /** Roughly two short paragraphs — long enough to be useful, capped so the card cannot dominate the page. */
    private const MAX_LENGTH = 500;

    /**
     * Authorization is handled by the admin gate in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One box per message rather than one per language — see TranslatableInput.
     * Both are stored under the fallback locale.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'warning' => TranslatableInput::toString($this->input('warning')),
            'advice' => TranslatableInput::toString($this->input('advice')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            // Both messages are optional — the whole feature can be left blank,
            // unlike a category name, which is required.
            'warning' => ['nullable', 'string', 'max:'.self::MAX_LENGTH],
            'advice' => ['nullable', 'string', 'max:'.self::MAX_LENGTH],
            /*
             * Bounded well away from zero: the rate is a divisor, and a near-zero
             * one turns a ៛20,000 coffee into a five-figure expense.
             *
             * `sometimes`, not `required` — this page is guidance copy first, and
             * a caller that only means to edit the messages should not have to
             * resend the rate to avoid clearing it.
             */
            'khr_per_usd' => ['sometimes', 'required', 'numeric', 'min:1', 'max:99999999.99'],
            // Which currency the amount fields start on. `sometimes` for the
            // same reason as the rate above.
            'default_currency' => ['sometimes', 'required', Rule::enum(Currency::class)],
        ];
    }

    /**
     * The columns to mass-assign, mirroring CategoryRequest::categoryAttributes().
     *
     * @return array<string, mixed>
     */
    public function spendingAttributes(): array
    {
        $attributes = [
            'spending_guidance_enabled' => $this->boolean('enabled'),
            'spending_warning' => $this->translations('warning'),
            'spending_advice' => $this->translations('advice'),
        ];

        // Omitted means "leave it as it is" — see the `sometimes` rules above.
        if ($this->has('khr_per_usd')) {
            $attributes['khr_per_usd'] = (float) $this->validated('khr_per_usd');
        }

        if ($this->has('default_currency')) {
            $attributes['default_currency'] = $this->validated('default_currency');
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'khr_per_usd' => __('exchange rate'),
        ];
    }

    /**
     * The value for one field as the per-locale map the column stores.
     *
     * A blank message clears the field to {} rather than storing '' — spatie
     * only falls back on an absent key, so an empty string would render as an
     * empty line instead of nothing at all.
     *
     * @return array<string, string>
     */
    private function translations(string $field): array
    {
        return TranslatableInput::toTranslations($this->input($field));
    }
}
