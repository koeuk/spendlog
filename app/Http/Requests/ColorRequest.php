<?php

namespace App\Http\Requests;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Both are now chosen from a set rather than typed, so the set is the
            // rule. Rule::enum still leaves the values as plain hex in the column
            // — the enums are the offered options, not a storage format.
            'button_color' => ['required', 'string', Rule::enum(ButtonColor::class)],
            'body_color' => ['required', 'string', Rule::enum(BodyColor::class)],
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
            'button_color.enum' => __('That is not one of the available button colours.'),
            'body_color.enum' => __('That is not one of the available backgrounds.'),
        ];
    }
}
