<?php

namespace App\Http\Requests;

use App\Support\Color;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ColorRequest extends FormRequest
{
    /** What APP_PAGE actually paints body text in (text-neutral-900). */
    private const PAGE_TEXT = '#171717';

    /** WCAG AA for body text. */
    private const MIN_TEXT_CONTRAST = 4.5;

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
            'body_color' => ['required', 'string', 'regex:'.Color::HEX_PATTERN, $this->lightEnough()],
        ];
    }

    /**
     * The light-mode page must stay light.
     *
     * This is not fussiness about taste — the light theme is built on a pale
     * page. Body text is near-black, and the cards are translucent white
     * (bg-white/60), so on a dark background the page text vanishes and the
     * cards turn light-grey with dark text inside them: a pale-card, dark-page
     * hybrid that is neither theme. Dark mode already does a dark page properly,
     * with fills and text designed for it, so there is nothing to gain here and a
     * broken screen to lose.
     */
    private function lightEnough(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            // Malformed hex is the regex rule's job — do not report it twice.
            if (! is_string($value) || ! Color::isHex($value)) {
                return;
            }

            if (Color::contrast($value, self::PAGE_TEXT) < self::MIN_TEXT_CONTRAST) {
                $fail(__('That colour is too dark for the light background — the page text would be unreadable on it. Dark mode already uses a dark page.'));
            }
        };
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
