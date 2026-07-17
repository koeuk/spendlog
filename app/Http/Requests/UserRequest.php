<?php

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Authorization is handled by UserPolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user?->getKey()),
            ],
            // Required on create, optional on edit — an admin editing a name
            // should not have to invent a new password to save the form.
            'password' => [
                $this->isCreating() ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
            // Only the assignable roles, never Rule::enum(RoleName::class) —
            // that would accept role=super_admin from a hand-made POST and let
            // any admin mint an account nobody can touch afterwards. Leaving it
            // out of the dropdown is presentation; this is the rule.
            'role' => ['required', Rule::in(array_column(RoleName::assignable(), 'value'))],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('Someone already uses that email address.'),
            'role.required' => __('Please pick a role.'),
        ];
    }

    public function isCreating(): bool
    {
        return $this->route('user') === null;
    }

    /**
     * Only the columns — role is applied separately via spatie, and password is
     * dropped when left blank so an edit does not overwrite it with null.
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        $data = $this->safe()->only(['name', 'email', 'status']);

        if (filled($this->input('password'))) {
            // The model's 'hashed' cast does the hashing.
            $data['password'] = $this->input('password');
        }

        return $data;
    }
}
