<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use App\Support\PasswordOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_requesting_a_reset_emails_a_six_digit_code(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.reset', ['email' => $user->email]));

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $notification->code);

            return true;
        });
    }

    public function test_enter_code_screen_can_be_rendered(): void
    {
        $this->get('/reset-password?email=someone%40example.com')->assertStatus(200);
    }

    public function test_password_can_be_reset_with_the_emailed_code(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'email' => $user->email,
                'code' => $notification->code,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));

            return true;
        });
    }

    public function test_a_wrong_code_does_not_reset_the_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'original-password']);

        $this->post('/forgot-password', ['email' => $user->email]);

        $this->post('/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    /** The same emailed code must not work twice. */
    public function test_a_code_is_single_use(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) use ($user) {
            $payload = [
                'email' => $user->email,
                'code' => $notification->code,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ];

            $this->post('/reset-password', $payload)->assertRedirect(route('login'));
            $this->post('/reset-password', $payload)->assertSessionHasErrors('code');

            return true;
        });
    }

    public function test_an_expired_code_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $this->travel(PasswordOtp::TTL_MINUTES + 1)->minutes();

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'email' => $user->email,
                'code' => $notification->code,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasErrors('code');

            return true;
        });
    }

    /**
     * Five wrong guesses burn the code: after that even the right one is
     * refused, so a million-guess sweep cannot find it.
     */
    public function test_the_guess_limit_locks_out_even_the_right_code(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) use ($user) {
            $wrong = $notification->code === '111111' ? '222222' : '111111';

            foreach (range(1, PasswordOtp::MAX_ATTEMPTS) as $i) {
                $this->post('/reset-password', [
                    'email' => $user->email,
                    'code' => $wrong,
                    'password' => 'new-password-123',
                    'password_confirmation' => 'new-password-123',
                ])->assertSessionHasErrors('code');
            }

            $this->post('/reset-password', [
                'email' => $user->email,
                'code' => $notification->code,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertSessionHasErrors('code');

            return true;
        });
    }

    /** Asking twice inside the resend window keeps the first code and sends nothing new. */
    public function test_requesting_again_too_soon_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasErrors('email');

        Notification::assertSentToTimes($user, PasswordOtpNotification::class, 1);
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
    }
}
