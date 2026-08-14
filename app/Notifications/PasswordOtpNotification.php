<?php

namespace App\Notifications;

use App\Support\PasswordOtp;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying the six-digit reset code — the OTP flow's replacement for
 * Laravel's reset-link notification.
 */
class PasswordOtpNotification extends Notification
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
            ->subject(__('Your :app password reset code', ['app' => config('app.name')]))
            ->line(__('Use this code to reset your password:'))
            // Markdown: rendered large and bold, the one thing to read.
            ->line('# '.$this->code)
            ->line(__('The code expires in :minutes minutes and works once.', ['minutes' => PasswordOtp::TTL_MINUTES]))
            ->line(__('If you did not request it, no one can change your password with this email alone — you can safely ignore it.'));
    }
}
