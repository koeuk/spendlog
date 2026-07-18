<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\UsernameRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Same rules an admin's form uses, so a handle is not valid on one
            // screen and rejected on the other.
            'username' => UsernameRules::for($this->user()->id),
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UsernameRules::messages();
    }

    /**
     * Blank normalised to null, so clearing the field releases the handle rather
     * than storing '' against the unique index.
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        return [
            ...$this->safe()->only(['name', 'email']),
            'username' => UsernameRules::normalize($this->input('username')),
        ];
    }
}
