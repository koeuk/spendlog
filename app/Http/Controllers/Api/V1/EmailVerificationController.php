<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Notifications\EmailVerificationOtpNotification;
use App\Support\EmailVerificationOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth
 *
 * The same six-digit email check the web sign-up uses, for API clients. Register
 * already mailed the first code; these two trade it for a verified account and
 * send another. The limits live in EmailVerificationOtp, shared with the web.
 */
class EmailVerificationController extends Controller
{
    /**
     * Verify email with the code
     *
     * Already verified is a 200 with the same shape, so a client retrying after
     * a lost response lands in the app rather than on an error.
     *
     * @bodyParam code string required The six digits from the email. Example: 483291
     *
     * @response 200 {"message": "Your email is verified.", "user": {"uuid": "0198f...", "email": "sam@example.com", "email_verified_at": "2026-09-29T10:00:00+00:00"}}
     * @response 422 scenario="wrong, expired or over-guessed code" {"message": "That code is not valid.", "errors": {"code": ["That code is not valid. It may have expired — you can request a new one."]}}
     *
     * @throws ValidationException
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $request->validate([
                'code' => 'required|digits:6',
            ]);

            if (! EmailVerificationOtp::verify($user->email, $request->code)) {
                throw ValidationException::withMessages([
                    'code' => [__('That code is not valid. It may have expired — you can request a new one.')],
                ]);
            }

            EmailVerificationOtp::consume($user->email);

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        }

        return response()->json([
            'message' => __('Your email is verified.'),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Resend the code
     *
     * One code per minute; a fresh request inside that window is a 422, not a
     * second email.
     *
     * @response 200 {"message": "We emailed you a new 6-digit code."}
     * @response 422 scenario="asked again too soon" {"message": "Please wait before retrying.", "errors": {"code": ["Please wait before retrying."]}}
     *
     * @throws ValidationException
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('Your email is already verified.')]);
        }

        $code = EmailVerificationOtp::issue($user->email);

        if ($code === null) {
            throw ValidationException::withMessages([
                'code' => [trans('passwords.throttled')],
            ]);
        }

        $user->notify(new EmailVerificationOtpNotification($code));

        return response()->json(['message' => __('We emailed you a new 6-digit code.')]);
    }
}
