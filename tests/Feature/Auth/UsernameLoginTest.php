<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Signing in with a username instead of an email.
 *
 * The username is an *alternative* credential, never a replacement: the column
 * is nullable, so requiring it would lock out every account that has not set one.
 * Both routes reach the same password check and the same enumeration-safe error.
 */
class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'email' => 'sam@example.com',
            'username' => 'sam',
            ...$attributes,
        ]);
    }

    protected function tearDown(): void
    {
        // The throttle key is per identifier+IP and the limiter store outlives a
        // test, so a run of failed attempts here would trip the next test.
        RateLimiter::clear('sam|127.0.0.1');
        RateLimiter::clear('sam@example.com|127.0.0.1');

        parent::tearDown();
    }

    public function test_a_user_can_sign_in_with_their_username(): void
    {
        $user = $this->user();

        $this->post('/login', [
            'email' => 'sam',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_can_still_sign_in_with_their_email(): void
    {
        // The whole point of "alternative": nothing about the old way changes.
        $user = $this->user();

        $this->post('/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_with_no_username_still_signs_in_by_email(): void
    {
        // Most rows are like this — the column is nullable and nobody is forced
        // to fill it in.
        $user = $this->user(['username' => null]);

        $this->post('/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_against_a_username_fails(): void
    {
        $this->user();

        $this->post('/login', [
            'email' => 'sam',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unknown_username_gives_the_same_error_as_a_wrong_password(): void
    {
        // Enumeration safety: "no such user" must not be distinguishable from
        // "wrong password", or the form answers "does this handle exist?".
        $this->user();

        $failed = __('auth.failed');

        // Unknown handle...
        $this->post('/login', ['email' => 'nobody', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => $failed]);

        RateLimiter::clear('nobody|127.0.0.1');
        $this->flushSession();

        // ...and a real handle with the wrong password say exactly the same thing.
        $this->post('/login', ['email' => 'sam', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => $failed]);

        $this->assertGuest();
    }

    public function test_a_suspended_account_cannot_sign_in_by_username_either(): void
    {
        // The status check sits after the password check on both paths; the
        // username route must not slip past it.
        $this->user(['status' => UserStatus::Suspended]);

        $this->post('/login', [
            'email' => 'sam',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_email_shaped_value_is_never_matched_against_the_username(): void
    {
        // The split is on '@', and UsernameRules bars '@' from a username — so a
        // value containing one can only ever be looked up as an email.
        $this->user(['username' => null, 'email' => 'sam@example.com']);

        $this->post('/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }
}
