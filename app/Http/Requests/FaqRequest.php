<?php

namespace App\Http\Requests;

use App\Enums\FaqStatus;
use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One FAQ entry: a question and an answer per locale, plus a draft/published
 * status. Authorization is handled by the manageFaqs gate in the controller.
 */
class FaqRequest extends FormRequest
{
    private const MAX_QUESTION = 255;

    private const MAX_ANSWER = 2000;

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
            'question' => ['array'],
            'answer' => ['array'],
            'status' => ['required', Rule::enum(FaqStatus::class)],
        ];

        foreach (Locale::cases() as $locale) {
            // The fallback locale is required: spatie falls back to it, so an
            // entry with no English would render blank for every reader whose
            // own locale the admin left empty. Other locales are optional.
            $required = $locale->value === $fallback ? 'required' : 'nullable';

            $rules["question.{$locale->value}"] = [$required, 'string', 'max:'.self::MAX_QUESTION];
            $rules["answer.{$locale->value}"] = [$required, 'string', 'max:'.self::MAX_ANSWER];
        }

        return $rules;
    }

    /**
     * The non-empty translations for one field, keyed by locale.
     *
     * Empty locales are dropped rather than stored as '' — spatie only falls
     * back when the key is absent, so a blank string would show an empty line
     * instead of the fallback the admin did fill in.
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
