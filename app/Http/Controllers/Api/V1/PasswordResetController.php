<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use App\Support\PasswordOtp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 *
 * The same OTP reset the web login uses, for API clients: request a six-digit
 * code by email, then trade code + new password for a reset. All the limits
 * live in one place (PasswordOtp), so the two doors cannot drift apart —
 * five guesses, ten minutes, one use, shared between web and API.
 */
class PasswordResetController extends Controller
{
    /**
     * Request a reset code
     *
     * Emails a six-digit code to the account. One code per minute per email;
     * a fresh request inside that window is a 422, not a second email.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: koeukkos@gmail.com
     *
     * @response 200 {"message": "We emailed you a 6-digit code."}
     * @response 422 scenario="unknown email, or asked again too soon" {"message": "We can't find a user with that email address.", "errors": {"email": ["We can't find a user with that email address."]}}
     *
     * @throws ValidationException
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [trans('passwords.user')],
            ]);
        }

        $code = PasswordOtp::issue($user->email);

        if ($code === null) {
            throw ValidationException::withMessages([
                'email' => [trans('passwords.throttled')],
            ]);
        }

        $user->notify(new PasswordOtpNotification($code));

        return response()->json(['message' => __('We emailed you a 6-digit code.')]);
    }

    /**
     * Reset with the code
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: koeukkos@gmail.com
     * @bodyParam code string required The six digits from the email. Example: 483291
     * @bodyParam password string required Example: a-much-better-one
     * @bodyParam password_confirmation string required Example: a-much-better-one
     *
     * @response 200 {"message": "Your password has been reset."}
     * @response 422 scenario="wrong, expired or over-guessed code" {"message": "That code is not valid.", "errors": {"code": ["That code is not valid. It may have expired — you can request a new one."]}}
     *
     * @throws ValidationException
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->email)->first();

        // One error for every way to be wrong — see the web NewPasswordController.
        if (! $user || ! PasswordOtp::verify($user->email, $request->code)) {
            throw ValidationException::withMessages([
                'code' => [__('That code is not valid. It may have expired — you can request a new one.')],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        PasswordOtp::consume($user->email);

        event(new PasswordReset($user));

        return response()->json(['message' => trans('passwords.reset')]);
    }
}
