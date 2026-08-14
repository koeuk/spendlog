<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PasswordOtp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step two of the OTP reset: the emailed code plus the new password.
 */
class NewPasswordController extends Controller
{
    /**
     * Display the enter-code-and-new-password view.
     *
     * The email rides in as a query parameter so a refresh keeps it; landing
     * here directly with none is fine too — the field is just empty.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => (string) $request->query('email', ''),
            'status' => session('status'),
        ]);
    }

    /**
     * Check the code and set the new password.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->email)->first();

        /*
         * One error for every way to be wrong — unknown email, wrong code,
         * expired code, guess limit reached. Distinguishing them would hand a
         * caller an oracle: "wrong code" alone confirms the email has a reset
         * in flight.
         */
        if (! $user || ! PasswordOtp::verify($user->email, $request->code)) {
            throw ValidationException::withMessages([
                'code' => [__('That code is not valid. It may have expired — you can request a new one.')],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            // Rotating it signs out every "remember me" session — whoever just
            // proved inbox ownership is the only one who stays in control.
            'remember_token' => Str::random(60),
        ])->save();

        PasswordOtp::consume($user->email);

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', trans('passwords.reset'));
    }
}
