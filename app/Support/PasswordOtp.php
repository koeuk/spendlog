<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The six-digit password-reset code.
 *
 * Reuses the password_reset_tokens table Laravel's link flow wrote to, and the
 * same discipline: only a hash of the code is stored, so the database cannot
 * leak a usable code. What a link's 64-character token got for free, a code
 * this short has to buy with limits — five guesses and a ten-minute life,
 * enforced here rather than trusted to the caller.
 */
class PasswordOtp
{
    /** Long enough to fetch a code from an inbox, short enough to be stale by the same evening. */
    public const TTL_MINUTES = 10;

    /**
     * A six-digit code has a million values; five tries makes guessing one a
     * 0.0005% shot. The counter lives in the cache keyed per email, and a fresh
     * code resets it — the limit is per code, not per lifetime.
     */
    public const MAX_ATTEMPTS = 5;

    /** Matches the link flow's old resend throttle, so the UX wording still fits. */
    public const RESEND_SECONDS = 60;

    /**
     * Create and store a fresh code for this email.
     *
     * Returns the plain code exactly once — it exists nowhere but the email
     * after this call returns. Null means "asked again too soon": the previous
     * code is kept, so a fast double-submit cannot invalidate the code that is
     * already on its way to the inbox.
     */
    public static function issue(string $email): ?string
    {
        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($existing && Carbon::parse($existing->created_at)->addSeconds(self::RESEND_SECONDS)->isFuture()) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        // A new code is a new set of five chances.
        RateLimiter::clear(self::attemptsKey($email));

        return $code;
    }

    /**
     * Whether this code is the one issued to this email, said while it still
     * counts: within its lifetime and under the guess limit.
     *
     * Only a wrong code burns an attempt. Expired or absent rows fail without
     * counting — those are dead ends already, and charging for them would let
     * a stale form lock someone out of the code they are about to request.
     */
    public static function verify(string $email, string $code): bool
    {
        if (RateLimiter::tooManyAttempts(self::attemptsKey($email), self::MAX_ATTEMPTS)) {
            return false;
        }

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || Carbon::parse($row->created_at)->addMinutes(self::TTL_MINUTES)->isPast()) {
            return false;
        }

        if (! Hash::check($code, $row->token)) {
            // Remembered for the code's own lifetime — there is nothing left
            // to protect once the code itself has expired.
            RateLimiter::hit(self::attemptsKey($email), self::TTL_MINUTES * 60);

            return false;
        }

        return true;
    }

    /**
     * Retire the code after a successful reset. Single use: the same emailed
     * code must not be able to reset the password twice.
     */
    public static function consume(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        RateLimiter::clear(self::attemptsKey($email));
    }

    private static function attemptsKey(string $email): string
    {
        return 'password-otp:'.mb_strtolower($email);
    }
}
