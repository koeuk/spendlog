<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\EmailVerificationOtpNotification;
use App\Support\EmailVerificationOtp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        RateLimiter::clear('api-login');
        RateLimiter::clear('email-verification-otp:sam@spendlog.test');
    }

    public function test_register_emails_a_code_that_verifies_over_the_api(): void
    {
        Notification::fake();

        $token = $this->postJson('/api/v1/register', [
            'name' => 'Sam',
            'email' => 'sam@spendlog.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'test',
        ])->assertCreated()
            ->assertJsonPath('user.email_verified_at', null)
            ->json('token');

        $user = User::where('email', 'sam@spendlog.test')->firstOrFail();

        $code = null;
        Notification::assertSentTo($user, EmailVerificationOtpNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $response = $this->withToken($token)
            ->postJson('/api/v1/email/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', 'sam@spendlog.test');

        // The client swaps its cached user for this one, so it must say verified.
        $this->assertNotNull($response->json('user.email_verified_at'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_wrong_code_is_a_422_on_code(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'sam@spendlog.test']);
        $code = EmailVerificationOtp::issue($user->email);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verify', ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verifying_an_already_verified_account_is_a_200(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/email/verify', ['code' => '123456'])
            ->assertOk()
            ->assertJsonStructure(['message', 'user' => ['email_verified_at']]);
    }

    public function test_the_code_can_be_resent_but_not_twice_a_minute(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'sam@spendlog.test']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verification-notification')->assertOk();
        $this->postJson('/api/v1/email/verification-notification')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        Notification::assertSentToTimes($user, EmailVerificationOtpNotification::class, 1);
    }

    public function test_both_endpoints_need_a_token(): void
    {
        $this->postJson('/api/v1/email/verify', ['code' => '123456'])->assertUnauthorized();
        $this->postJson('/api/v1/email/verification-notification')->assertUnauthorized();
    }
}
