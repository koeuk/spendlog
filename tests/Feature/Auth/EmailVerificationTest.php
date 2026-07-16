<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * The 'verified' middleware is a silent no-op unless User implements
     * MustVerifyEmail, so this pins the behaviour rather than the wiring.
     */
    public function test_unverified_users_cannot_reach_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_can_reach_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_registering_sends_a_verification_email(): void
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
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
