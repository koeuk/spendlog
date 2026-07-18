<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * One field, either credential. Not validated as an email: a username
             * is a perfectly good value here, and `email` would reject it before
             * the attempt is ever made.
             *
             * Still named `email` on the wire. Renaming it would break every
             * saved password manager entry and every existing client, and this
             * request's error bag is keyed on it throughout — including the
             * status and throttle messages below, which the login page renders
             * under this field.
             */
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /*
         * Checked after the password, not before: refusing a suspended account
         * earlier would answer "is this address suspended?" to anyone who asked,
         * which is a user-enumeration oracle. Getting here means the credentials
         * were already correct.
         */
        if (! Auth::user()->status->canSignIn()) {
            // The status supplies its own wording — "suspended" is wrong for an
            // account that was archived or never handed over.
            $message = Auth::user()->status->signInError();

            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => $message]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * The submitted identifier resolved to the column it belongs to.
     *
     * Deliberately not "try email, then try username": two attempts means two
     * password hashes computed, and the timing difference between a hit on the
     * first and a hit on the second is measurable. One column, one attempt.
     *
     * A value containing '@' is an email — usernames cannot contain one, which
     * is exactly why UsernameRules excludes it.
     *
     * @return array<string, string>
     */
    protected function credentials(): array
    {
        $login = (string) $this->string('email');

        return [
            str_contains($login, '@') ? 'email' : 'username' => $login,
            'password' => (string) $this->string('password'),
        ];
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
