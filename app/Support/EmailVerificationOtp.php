<?php

namespace App\Support;

/**
 * The six-digit code that confirms a new account's email address.
 *
 * Every rule is PasswordOtp's — hashed at rest, ten minutes, five guesses, one
 * resend a minute — only the table and the guess counter are its own, so a
 * signup code can never be spent on a password reset, nor the other way round.
 */
class EmailVerificationOtp extends PasswordOtp
{
    protected const TABLE = 'email_verification_codes';

    protected const KEY_PREFIX = 'email-verification-otp';
}
