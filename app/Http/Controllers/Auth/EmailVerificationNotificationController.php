<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\EmailVerificationOtpNotification;
use App\Support\EmailVerificationOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new six-digit verification code.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($user->homeRoute(), absolute: false));
        }

        // Issued here rather than through sendEmailVerificationNotification so
        // "too soon" can be said out loud instead of silently sending nothing.
        $code = EmailVerificationOtp::issue($user->email);

        if ($code === null) {
            throw ValidationException::withMessages([
                'code' => [trans('passwords.throttled')],
            ]);
        }

        $user->notify(new EmailVerificationOtpNotification($code));

        return back()->with('status', 'verification-code-sent');
    }
}
