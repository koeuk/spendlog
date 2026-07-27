<?php

namespace App\Http\Requests;

use App\Enums\FaqStatus;
use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One FAQ entry: a question, an answer and a draft/published status.
 * Authorization is handled by the manageFaqs gate in the controller.
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
     * One box per field rather than one per language — see TranslatableInput.
     * Both are stored under the fallback locale.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'question' => TranslatableInput::toString($this->input('question')),
            'answer' => TranslatableInput::toString($this->input('answer')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.self::MAX_QUESTION],
            'answer' => ['required', 'string', 'max:'.self::MAX_ANSWER],
            'status' => ['required', Rule::enum(FaqStatus::class)],
        ];
    }

    /**
     * The value for one field as the per-locale map the column stores.
     *
     * @return array<string, string>
     */
    public function translationsFor(string $field): array
    {
        return TranslatableInput::toTranslations($this->input($field));
    }
}
