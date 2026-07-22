<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Sign in with Google.
 *
 * A second door onto the same accounts, not a parallel identity system: a
 * Google sign-in lands on a row in `users` with the same role and the same
 * permissions it would have had from the register form, because permissions are
 * the only thing the policies read.
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        // Reachable by typing the URL even when the button is hidden, so the
        // guard is here rather than only in the view.
        if (! self::configured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in is not available.'),
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! self::configured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in is not available.'),
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            // A denied consent screen and a misconfigured client both land here.
            // The user gets one message; the detail goes to the log, where it is
            // some use, rather than onto the login page, where it is not.
            Log::warning('Google sign-in failed.', ['exception' => $e]);

            return redirect()->route('login')->withErrors([
                'email' => __('Could not sign you in with Google. Please try again.'),
            ]);
        }

        $email = $googleUser->getEmail();

        // Google can return a verified account with no address exposed. Nothing
        // downstream works without one — it is the login identifier.
        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => __('Your Google account did not share an email address.'),
            ]);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            /*
             * Linking an existing password account on first Google sign-in.
             *
             * Safe because Google has verified the address and the address is
             * unique in this table — the person proved control of the mailbox
             * the account was registered with. Refusing to link would instead
             * strand them: same address, two accounts, no way to merge.
             */
            if (! $user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->save();
            }
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                // No password. The column is nullable for exactly this row, and
                // a placeholder hash would look like a credential to try.
                'password' => null,
            ]);

            // Same as RegisteredUserController: without a role the account can
            // do nothing at all, not even open the dashboard.
            $user->applyRole(RoleName::User);

            // Google has already verified it, so the account skips the email
            // round-trip it would face coming through the register form.
            $user->forceFill(['email_verified_at' => now()])->save();

            event(new Registered($user));
        }

        /*
         * Checked after the account is resolved, mirroring LoginRequest: a
         * suspended account is refused at the door rather than being allowed a
         * session because it came in through a provider.
         */
        if (! $user->status->canSignIn()) {
            return redirect()->route('login')->withErrors([
                'email' => $user->status->signInError(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route($user->homeRoute(), absolute: false));
    }

    /**
     * Whether a deployment has been given Google credentials.
     *
     * Read in HandleInertiaRequests too, so the button is absent rather than
     * offered and then dead-ending at the provider.
     */
    public static function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
