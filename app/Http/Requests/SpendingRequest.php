<?php

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The dashboard spending-guidance copy: an enabled flag plus a warning and an
 * advice message, each a translatable field — the same shape CategoryRequest
 * validates for a category name.
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            // Every locale is optional — the whole feature can be left blank,
            // unlike a category name where English is required.
            'warning' => ['array'],
            'advice' => ['array'],
            /*
             * Bounded well away from zero: the rate is a divisor, and a near-zero
             * one turns a ៛20,000 coffee into a five-figure expense.
             *
             * `sometimes`, not `required` — this page is guidance copy first, and
             * a caller that only means to edit the messages should not have to
             * resend the rate to avoid clearing it.
             */
            'khr_per_usd' => ['sometimes', 'required', 'numeric', 'min:1', 'max:99999999.99'],
        ];

        foreach (Locale::cases() as $locale) {
            $rules["warning.{$locale->value}"] = ['nullable', 'string', 'max:'.self::MAX_LENGTH];
            $rules["advice.{$locale->value}"] = ['nullable', 'string', 'max:'.self::MAX_LENGTH];
        }

        return $rules;
    }

    /**
     * The columns to mass-assign, mirroring CategoryRequest::categoryAttributes().
     *
     * @return array<string, mixed>
     */
    public function spendingAttributes(): array
    {
        return [
            'spending_guidance_enabled' => $this->boolean('enabled'),
            'spending_warning' => $this->translations('warning'),
            'spending_advice' => $this->translations('advice'),
            'khr_per_usd' => (float) $this->validated('khr_per_usd'),
        ];
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
     * The non-empty translations for one field, keyed by locale.
     *
     * Blank locales are dropped rather than stored as '' — the same array_filter
     * CategoryRequest uses — so spatie can fall back to another locale instead of
     * showing an empty line, and an all-blank field clears to {}.
     *
     * @return array<string, string>
     */
    private function translations(string $field): array
    {
        return array_filter(
            (array) $this->input($field, []),
            fn ($value, $locale) => filled($value) && Locale::tryFrom($locale),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
