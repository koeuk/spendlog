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

        if ($user && ! $user->status->canSignIn()) {
            $message = $user->status->signInError();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Tokens are revoked too, or a suspended user's phone keeps working.
            $user->tokens()->delete();

            return redirect()
                ->route('login')
                ->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
