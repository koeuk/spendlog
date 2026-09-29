<?php

namespace App\Notifications;

use App\Support\EmailVerificationOtp;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying the six-digit signup code — the OTP flow's replacement for
 * Laravel's verify-link notification.
 */
class EmailVerificationOtpNotification extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your :app verification code', ['app' => config('app.name')]))
            ->line(__('Use this code to confirm your email address:'))
            // Markdown: rendered large and bold, the one thing to read.
            ->line('# '.$this->code)
            ->line(__('The code expires in :minutes minutes and works once.', ['minutes' => EmailVerificationOtp::TTL_MINUTES]))
            ->line(__('If you did not create an account, you can safely ignore this email.'));
    }
}
