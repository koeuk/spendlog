<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\EmailVerificationOtpNotification;
use App\Support\EmailVerificationOtp;
use App\Support\PasswordOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The guess counter is a cache entry that outlives a test in the same
        // process; the throttle middleware keys on the user, whose id repeats.
        RateLimiter::clear('email-verification-otp:unverified@spendlog.test');
    }

    private function unverifiedUser(): User
    {
        return User::factory()->unverified()->create(['email' => 'unverified@spendlog.test']);
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified_with_the_emailed_code(): void
    {
        $user = $this->unverifiedUser();
        $code = EmailVerificationOtp::issue($user->email);

        Event::fake();

        $response = $this->actingAs($user)->post('/verify-email', ['code' => $code]);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_a_wrong_code(): void
    {
        $user = $this->unverifiedUser();
        $code = EmailVerificationOtp::issue($user->email);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->actingAs($user)
            ->post('/verify-email', ['code' => $wrong])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->unverifiedUser();
        $code = EmailVerificationOtp::issue($user->email);

        $this->travel(EmailVerificationOtp::TTL_MINUTES + 1)->minutes();

        $this->actingAs($user)
            ->post('/verify-email', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Separate tables: a password-reset code for the same address must not
     * verify it, or anyone mid-reset would verify by accident — and the other
     * way round would let a signup code reset a password.
     */
    public function test_a_password_reset_code_does_not_verify_the_email(): void
    {
        $user = $this->unverifiedUser();
        $code = PasswordOtp::issue($user->email);

        $this->actingAs($user)
            ->post('/verify-email', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_code_can_be_resent_but_not_twice_a_minute(): void
    {
        Notification::fake();

        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-code-sent');

        Notification::assertSentToTimes($user, EmailVerificationOtpNotification::class, 1);

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertSessionHasErrors('code');

        Notification::assertSentToTimes($user, EmailVerificationOtpNotification::class, 1);
    }

    /**
     * The 'verified' middleware is a silent no-op unless User implements
     * MustVerifyEmail, so this pins the behaviour rather than the wiring.
     */
    public function test_unverified_users_cannot_reach_the_dashboard(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_can_reach_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_registering_emails_a_code_that_verifies_the_account(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Newbie',
            'email' => 'newbie@spendlog.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'newbie@spendlog.test')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());

        $code = null;
        Notification::assertSentTo($user, EmailVerificationOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return preg_match('/^\d{6}$/', $code) === 1;
        });

        // Fresh from the database, as the next real request would load it.
        $this->actingAs($user)->post('/verify-email', ['code' => $code]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
