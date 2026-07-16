<?php

namespace App\Http\Requests;

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

            // SVG is deliberately not accepted. An SVG can carry <script>, and
            // these are served from our own origin, so an uploaded one would run
            // in the app's security context.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=1024,max_height=1024'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,webp', 'max:256', 'dimensions:max_width=512,max_height=512'],

            // Sent instead of a file to clear an existing image.
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'app_name.required' => __('The app name is required.'),
            'app_name.max' => __('Keep the app name under 50 characters — it has to fit the nav bar.'),
            'logo.mimes' => __('The logo must be a PNG, JPG or WebP file.'),
            'logo.max' => __('The logo must be smaller than 1 MB.'),
            'logo.dimensions' => __('The logo must be no larger than 1024×1024.'),
            'favicon.mimes' => __('The favicon must be a PNG, ICO or WebP file.'),
            'favicon.max' => __('The favicon must be smaller than 256 KB.'),
            'favicon.dimensions' => __('The favicon must be no larger than 512×512.'),
        ];
    }
}
