<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The self-service account endpoints: profile, password, and the OTP reset —
 * the API side of what the web settings and login pages already do.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    // ----------------------------------------------------------- profile

    public function test_a_user_updates_their_own_profile(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::ProfileWrite->value]);

        $this->patchJson('/api/v1/profile', [
            'name' => 'New Name',
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_changing_the_email_clears_verification(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::ProfileWrite->value]);

        $this->patchJson('/api/v1/profile', [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_token_without_the_ability_cannot_touch_the_profile(): void
    {
        $user = $this->user();

        // A deliberately narrow token — say, a read-only dashboard widget.
        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->patchJson('/api/v1/profile', [
            'name' => 'Hijacked',
            'email' => $user->email,
        ])->assertForbidden();

        $this->assertNotSame('Hijacked', $user->fresh()->name);
    }

    public function test_the_default_token_of_a_regular_user_carries_profile_write(): void
    {
        $user = $this->user();

        $this->assertContains(
            TokenAbility::ProfileWrite->value,
            TokenAbility::defaults($user),
        );
    }

    // ---------------------------------------------------------- password

    public function test_a_user_changes_their_password(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::ProfileWrite->value]);

        $this->putJson('/api/v1/password', [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::ProfileWrite->value]);

        $this->putJson('/api/v1/password', [
            'password' => 'brand-new-password',
            'password_confirmation' => 'something-else',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // --------------------------------------------------------- OTP reset

    public function test_the_api_reset_flow_works_end_to_end(): void
    {
        Notification::fake();

        $user = $this->user();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) use ($user) {
            $this->postJson('/api/v1/reset-password', [
                'email' => $user->email,
                'code' => $notification->code,
                'password' => 'reset-by-api-123',
                'password_confirmation' => 'reset-by-api-123',
            ])->assertOk();

            $this->assertTrue(Hash::check('reset-by-api-123', $user->fresh()->password));

            return true;
        });
    }

    public function test_a_wrong_code_is_a_422(): void
    {
        Notification::fake();

        $user = $this->user();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'reset-by-api-123',
            'password_confirmation' => 'reset-by-api-123',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /**
     * The guess counter is shared with the web flow — burning the code on one
     * door burns it on both, or an attacker would simply alternate doors for
     * ten free guesses.
     */
    public function test_web_and_api_guesses_share_one_limit(): void
    {
        Notification::fake();

        $user = $this->user();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordOtpNotification::class, function ($notification) use ($user) {
            $wrong = $notification->code === '111111' ? '222222' : '111111';

            // Three guesses through the API…
            foreach (range(1, 3) as $i) {
                $this->postJson('/api/v1/reset-password', [
                    'email' => $user->email,
                    'code' => $wrong,
                    'password' => 'reset-by-api-123',
                    'password_confirmation' => 'reset-by-api-123',
                ])->assertStatus(422);
            }

            // …two through the web form…
            foreach (range(1, 2) as $i) {
                $this->post('/reset-password', [
                    'email' => $user->email,
                    'code' => $wrong,
                    'password' => 'reset-by-api-123',
                    'password_confirmation' => 'reset-by-api-123',
                ])->assertSessionHasErrors('code');
            }

            // …and the five together have burned the code for good.
            $this->postJson('/api/v1/reset-password', [
                'email' => $user->email,
                'code' => $notification->code,
                'password' => 'reset-by-api-123',
                'password_confirmation' => 'reset-by-api-123',
            ])->assertStatus(422);

            return true;
        });
    }
}
