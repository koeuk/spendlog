<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\EmailVerificationOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified, given the
     * six-digit code that was mailed to it.
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($user->homeRoute(), absolute: false).'?verified=1');
        }

        $request->validate([
            'code' => 'required|digits:6',
        ]);

        // One message for wrong, expired and out-of-guesses, as on the reset.
        if (! EmailVerificationOtp::verify($user->email, $request->code)) {
            throw ValidationException::withMessages([
                'code' => [__('That code is not valid. It may have expired — you can request a new one.')],
            ]);
        }

        EmailVerificationOtp::consume($user->email);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route($user->homeRoute(), absolute: false).'?verified=1');
    }
}
