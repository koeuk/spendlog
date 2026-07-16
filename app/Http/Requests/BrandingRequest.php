<?php

namespace App\Http\Requests;

use App\Support\Color;
use Illuminate\Foundation\Http\FormRequest;

class BrandingRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:50'],

            // No dimension limit: the img tags scale with object-contain, so any
            // size renders fine. The file-size cap is what actually protects the
            // page weight.
            //
            // SVG is deliberately not accepted. An SVG can carry <script>, and
            // these are served from our own origin, so an uploaded one would run
            // in the app's security context.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,webp,jpg,jpeg', 'max:1024'],

            // Sent instead of a file to clear an existing image.
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],

            // Strict '#rrggbb'. These end up inside a CSS custom property, so
            // anything looser is a stylesheet injection: a value like
            // "red;} html{display:none" would otherwise escape the declaration.
            // Not `regex:` with a hand-written pattern — one shared constant, so
            // the rule and Color's parser can never disagree about what is valid.
            'button_color' => ['required', 'string', 'regex:'.Color::HEX_PATTERN],
            'body_color' => ['required', 'string', 'regex:'.Color::HEX_PATTERN],
        ];
    }

    /**
     * Browsers send #RRGGBB from <input type="color"> in lower case, but a typed
     * value may not be. Normalising here keeps one canonical form in the column,
     * so preset comparison in the UI is a plain string match.
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
            'app_name.required' => __('The app name is required.'),
            'app_name.max' => __('Keep the app name under 50 characters — it has to fit the nav bar.'),
            'logo.mimes' => __('The logo must be a PNG, JPG or WebP file.'),
            'logo.max' => __('The logo must be smaller than 2 MB.'),
            'favicon.mimes' => __('The favicon must be a PNG, ICO, JPG or WebP file.'),
            'favicon.max' => __('The favicon must be smaller than 1 MB.'),
            'button_color.regex' => __('The button colour must be a hex value like #4b9d5f.'),
            'body_color.regex' => __('The body colour must be a hex value like #faf8f4.'),
        ];
    }
}
