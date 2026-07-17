<?php

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The dashboard spending-guidance copy: an enabled flag plus a warning and an
 * advice message, each per locale.
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
            // present, not required: an empty message is a valid choice (show
            // only the other one, or none). The nested locale keys carry the
            // real constraints.
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
     * The non-empty translations for one field, keyed by locale.
     *
     * Empty locales are dropped rather than stored as '': spatie only falls back
     * to another locale when the key is absent, so keeping a blank string here
     * would show a Khmer reader an empty line instead of the English an admin did
     * fill in. Returning [] clears the field entirely.
     *
     * @return array<string, string>
     */
    public function translationsFor(string $field): array
    {
        $values = [];

        foreach (Locale::cases() as $locale) {
            $value = trim((string) $this->input("{$field}.{$locale->value}", ''));

            if ($value !== '') {
                $values[$locale->value] = $value;
            }
        }

        return $values;
    }
}
