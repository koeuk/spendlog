<?php

namespace App\Http\Requests;

use App\Support\Color;
use Illuminate\Foundation\Http\FormRequest;

class ColorRequest extends FormRequest
{
    /**
     * Authorization is handled by the admin gate in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Strict '#rrggbb'. These end up inside a CSS custom property, so
            // anything looser is a stylesheet injection: a value like
            // "red;} html{display:none" would otherwise escape the declaration.
            // The pattern is shared with Color, so the rule and the parser can
            // never disagree about what is valid.
            'button_color' => ['required', 'string', 'regex:'.Color::HEX_PATTERN],
            'body_color' => ['required', 'string', 'regex:'.Color::HEX_PATTERN],
        ];
    }

    /**
     * Browsers send #RRGGBB from <input type="color"> in lower case, but a typed
     * or pasted value may not be. Normalising here keeps one canonical form in
     * the column, so comparing against a preset stays a plain string match.
     */
    protected function prepareForValidation(): void
    {
        foreach (['button_color', 'body_color'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => mb_strtolower(trim($this->input($field)))]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'button_color.regex' => __('The button colour must be a hex value like #4b9d5f.'),
            'body_color.regex' => __('The background colour must be a hex value like #faf8f4.'),
        ];
    }
}
