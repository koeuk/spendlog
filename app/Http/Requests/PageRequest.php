<?php

namespace App\Http\Requests;

use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One footer page: a title, a body and a published flag. Authorization is the
 * managePages gate in the controller.
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
     * One box per field rather than one per language — see TranslatableInput.
     * Both are stored under the fallback locale.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => TranslatableInput::toString($this->input('title')),
            'body' => TranslatableInput::toString($this->input('body')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Filled in only when the page is published: a draft may be half-written,
        // but a live page has to show something.
        $required = $this->boolean('published') ? 'required' : 'nullable';

        return [
            'title' => [$required, 'string', 'max:'.self::MAX_TITLE],
            'body' => [$required, 'string', 'max:'.self::MAX_BODY],
            'published' => ['required', 'boolean'],
        ];
    }

    /**
     * The value for one field as the per-locale map the column stores. Blank
     * clears it rather than storing "" — spatie only falls back on an absent key.
     *
     * @return array<string, string>
     */
    public function translationsFor(string $field): array
    {
        return TranslatableInput::toTranslations($this->input($field));
    }
}
