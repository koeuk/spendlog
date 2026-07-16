<?php

namespace App\Http\Requests;

use App\Enums\BodyColor;
use App\Support\Color;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ColorRequest extends FormRequest
{
    /** The only two label colours a button ever uses. */
    private const LABEL_DARK = '#0a0a0a';

    private const LABEL_LIGHT = '#fafafa';

    /** WCAG AA for the button's label. */
    private const MIN_LABEL_CONTRAST = 4.5;

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
            /*
             * The two fields are validated differently on purpose.
             *
             * The button takes any hex that can carry a readable label — the
             * presets are a shortcut, not a fence, so a brand colour that is not
             * on the list is still fair game.
             *
             * The background is the list. Every surface and text token in the
             * theme is derived from it, so an unusable value breaks the whole
             * page rather than one control; and the offered five are all that the
             * light theme is built to sit on.
             */
            'button_color' => ['required', 'string', 'regex:'.Color::HEX_PATTERN, $this->labelIsReadable()],
            'body_color' => ['required', 'string', Rule::enum(BodyColor::class)],
        ];
    }

    /**
     * The button has to be able to carry a label.
     *
     * A label is near-black or near-white — there is no third option — so a fill
     * that contrasts with neither cannot be labelled at all. Some mid-tones are
     * exactly that: #ad661f peaks at 4.43:1 against *both* ends. No amount of
     * picking the better one fixes it, which is why this is a refusal rather than
     * a warning.
     *
     * The failing band is narrower than it looks, so it is measured rather than
     * guessed at: a saturated #d92626 seems like it belongs here and does not —
     * it labels at 4.73 and is allowed.
     */
    private function labelIsReadable(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            // Malformed hex is the regex rule's job — do not report it twice.
            if (! is_string($value) || ! Color::isHex($value)) {
                return;
            }

            $best = max(
                Color::contrast($value, self::LABEL_DARK),
                Color::contrast($value, self::LABEL_LIGHT),
            );

            if ($best < self::MIN_LABEL_CONTRAST) {
                $fail(__('No label is readable on that colour (:ratio:1, needs :needs:1). Try a darker or lighter shade of it.', [
                    'ratio' => number_format($best, 1),
                    'needs' => self::MIN_LABEL_CONTRAST,
                ]));
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
            'button_color.enum' => __('That is not one of the available button colours.'),
            'body_color.enum' => __('That is not one of the available backgrounds.'),
        ];
    }
}
