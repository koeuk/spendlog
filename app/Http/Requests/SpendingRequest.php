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
