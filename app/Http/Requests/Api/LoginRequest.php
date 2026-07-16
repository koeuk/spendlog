<?php

namespace App\Http\Requests\Api;

use App\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Separate from App\Http\Requests\Auth\LoginRequest, which is session-based:
 * it regenerates the session and uses Auth::attempt. Token auth shares neither.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Names the token in the user's token list, so it can be revoked
            // individually later ("iPhone 15" rather than "token #4").
            'device_name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', Rule::enum(TokenAbility::class)],
        ];
    }
}
