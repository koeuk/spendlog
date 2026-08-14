<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use App\Support\PasswordOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step one of the OTP reset: ask for an email, send a six-digit code to it.
 * The name is inherited from the Breeze link flow this replaced, so the route
 * names (password.request / password.email) and their tests stay put.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Email a reset code and move on to the enter-code screen.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Same response as the old broker: an unknown address is said out
        // loud. The register page already answers "is this email taken?", so
        // silence here would cost usability without buying any secrecy.
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [trans('passwords.user')],
            ]);
        }

        $code = PasswordOtp::issue($user->email);

        // Null means a code was already sent moments ago and still stands —
        // resending would only race the one already in the inbox.
        if ($code === null) {
            throw ValidationException::withMessages([
                'email' => [trans('passwords.throttled')],
            ]);
        }

        $user->notify(new PasswordOtpNotification($code));

        return redirect()
            ->route('password.reset', ['email' => $user->email])
            ->with('status', __('We emailed you a 6-digit code.'));
    }
}
