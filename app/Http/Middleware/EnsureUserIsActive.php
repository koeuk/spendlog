<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account suspended after it signed in.
 *
 * The login check alone is not enough: suspending someone who is already working
 * would otherwise leave them with a live session until it expired — which, at the
 * default 120-minute lifetime, is two more hours of access after being cut off.
 * This runs on every authenticated request, so the next click is the last one.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->status->canSignIn()) {
            return $next($request);
        }

        $message = $user->status->signInError();

        // Tokens are revoked on either surface, or a suspended user's phone
        // keeps working. This is the only checkpoint a token-only client ever
        // reaches: status is not carried on the token, so nothing further down
        // the stack would notice.
        $user->tokens()->delete();

        /*
         * A token client has no session to invalidate and nowhere to be
         * redirected to — a 302 to /login would be parsed as a successful
         * response body. It gets the same refusal the login endpoint gives.
         */
        if ($this->isTokenRequest($request)) {
            return response()->json(['message' => $message], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }

    /**
     * Session requests carry a session; token requests do not. Checking the
     * session rather than expectsJson() keeps an Inertia XHR — which does want
     * JSON — on the redirect path where it belongs.
     */
    private function isTokenRequest(Request $request): bool
    {
        return ! $request->hasSession() || $request->user()?->currentAccessToken() !== null;
    }
}
