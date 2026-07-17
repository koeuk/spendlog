<?php

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One footer page: a title and a body per locale, plus a published flag.
 * Authorization is the managePages gate in the controller.
 */
class PageRequest extends FormRequest
{
    private const MAX_TITLE = 120;

    private const MAX_BODY = 20000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fallback = config('app.fallback_locale');

        $rules = [
            'title' => ['array'],
            'body' => ['array'],
            'published' => ['required', 'boolean'],
        ];

        foreach (Locale::cases() as $locale) {
            // The fallback locale is required only when the page is published:
            // a draft may be half-written, but a live page has to show something.
            $required = $locale->value === $fallback && $this->boolean('published')
                ? 'required'
                : 'nullable';

            $rules["title.{$locale->value}"] = [$required, 'string', 'max:'.self::MAX_TITLE];
            $rules["body.{$locale->value}"] = [$required, 'string', 'max:'.self::MAX_BODY];
        }

        return $rules;
    }

    /**
     * The non-empty translations for one field, keyed by locale. Empty locales
     * are dropped so spatie falls back rather than showing a blank line.
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
