<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileAvatarRequest extends FormRequest
{
    /**
     * Any signed-in account may set its own photo — the same rule as the API's
     * profile/avatar endpoints, which ask for no ability or permission either.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The same limits as the API endpoints (Api\V1\ProfileController),
            // so a photo accepted at one door is accepted at the other. SVG is
            // deliberately not accepted, for the reason BrandingRequest gives:
            // it can carry a script, and it would be served from our origin.
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => __('Choose a photo to upload.'),
            'avatar.image' => __('The photo must be a JPEG, PNG or WebP file.'),
            'avatar.mimes' => __('The photo must be a JPEG, PNG or WebP file.'),
            'avatar.max' => __('The photo must be smaller than 4 MB.'),
        ];
    }
}
