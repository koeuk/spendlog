<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The admin desk over the API: users, FAQs, spending settings. The double
 * gate is the point under test — the ability limits the client, the policy
 * limits the user, and both must pass.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);

        return $admin;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    // ------------------------------------------------------------------ users

    public function test_an_admin_lists_users_with_role_and_status(): void
    {
        $admin = $this->admin();
        $this->user();

        Sanctum::actingAs($admin, [TokenAbility::UsersRead->value]);

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['uuid', 'name', 'email', 'role', 'status']]]);
    }

    public function test_an_admin_sets_and_removes_a_users_photo(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $user = $this->user();

        Sanctum::actingAs($admin, [TokenAbility::UsersWrite->value]);

        $this->post("/api/v1/admin/users/{$user->uuid}/avatar", [
            'avatar' => UploadedFile::fake()->image('them.jpg', 300, 300),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.avatar_url', fn ($url) => str_contains($url, '/storage/avatars/'));

        $path = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/v1/admin/users/{$user->uuid}/avatar")
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_a_regular_user_cannot_set_someone_elses_photo(): void
    {
        Storage::fake('public');

        $me = $this->user();
        $them = $this->user();

        // Even with the ability forged onto the token, the policy still says no.
        Sanctum::actingAs($me, [TokenAbility::UsersWrite->value]);

        $this->post("/api/v1/admin/users/{$them->uuid}/avatar", [
            'avatar' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertNull($them->fresh()->avatar_path);
    }

    public function test_a_regular_users_token_never_carries_the_users_abilities(): void
    {
        $abilities = TokenAbility::defaults($this->user());

        $this->assertNotContains(TokenAbility::UsersRead->value, $abilities);
        $this->assertNotContains(TokenAbility::UsersWrite->value, $abilities);
        $this->assertNotContains(TokenAbility::SettingsWrite->value, $abilities);
    }

    public function test_an_admin_creates_a_user_over_the_api(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::UsersWrite->value]);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Sok Dara',
            'email' => 'dara@example.com',
            'username' => 'dara',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
            'role' => RoleName::User->value,
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', RoleName::User->value);

        $this->assertTrue(
            User::where('email', 'dara@example.com')->first()->hasRole(RoleName::User->value),
        );
    }

    public function test_the_api_cannot_mint_a_super_admin(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::UsersWrite->value]);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
            'role' => 'super_admin',
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_suspending_over_the_api_revokes_the_targets_tokens(): void
    {
        $target = $this->user();
        $target->createToken('their phone');

        Sanctum::actingAs($this->admin(), [TokenAbility::UsersWrite->value]);

        $this->patchJson('/api/v1/admin/users/'.$target->uuid, [
            'name' => $target->name,
            'email' => $target->email,
            'role' => RoleName::User->value,
            'status' => 'suspended',
        ])->assertOk();

        $this->assertSame(0, $target->fresh()->tokens()->count());
    }

    public function test_an_admin_deletes_a_user(): void
    {
        $target = $this->user();

        Sanctum::actingAs($this->admin(), [TokenAbility::UsersWrite->value]);

        $this->deleteJson('/api/v1/admin/users/'.$target->uuid)->assertNoContent();
        $this->assertNull(User::find($target->id));
    }

    // ------------------------------------------------------------------- faqs

    public function test_everyone_reads_published_faqs_admins_read_all(): void
    {
        Faq::create(['question' => ['en' => 'Q1'], 'answer' => ['en' => 'A1'], 'status' => 'published', 'position' => 1]);
        Faq::create(['question' => ['en' => 'Q2'], 'answer' => ['en' => 'A2'], 'status' => 'draft', 'position' => 2]);

        Sanctum::actingAs($this->user(), [TokenAbility::DashboardRead->value]);
        $this->getJson('/api/v1/faqs')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);
        $this->getJson('/api/v1/faqs')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_admin_manages_faqs(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $created = $this->postJson('/api/v1/admin/faqs', [
            'question' => 'How do budgets work?',
            'answer' => 'One slot per category per month.',
            'status' => 'published',
        ])->assertCreated()->json('data');

        $this->patchJson('/api/v1/admin/faqs/'.$created['uuid'], [
            'question' => 'How do budgets really work?',
            'answer' => 'One slot per category per month.',
            'status' => 'draft',
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $this->deleteJson('/api/v1/admin/faqs/'.$created['uuid'])->assertNoContent();
    }

    // --------------------------------------------------------------- settings

    public function test_an_admin_updates_the_exchange_rate(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $this->putJson('/api/v1/admin/settings/spending', [
            'enabled' => true,
            'warning' => 'Spend less.',
            'advice' => 'Save first.',
            'khr_per_usd' => 4150,
        ])->assertOk()->assertJsonPath('data.khr_per_usd', 4150);

        $this->getJson('/api/v1/admin/settings/spending')
            ->assertOk()
            ->assertJsonPath('data.khr_per_usd', 4150);
    }

    public function test_a_non_admin_with_a_forged_ability_is_still_refused(): void
    {
        // Sanctum::actingAs can fake abilities a real login never grants —
        // exactly the situation the policies exist to stop.
        Sanctum::actingAs($this->user(), [
            TokenAbility::UsersRead->value,
            TokenAbility::SettingsWrite->value,
        ]);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/settings/spending')->assertForbidden();
    }

    public function test_any_signed_in_account_reads_the_money_settings(): void
    {
        // Not admin-only: a client needs the rate to show what riel amount it
        // is about to send, and only an admin may change it.
        $user = $this->user();

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/settings/money')
            ->assertOk()
            ->assertJsonStructure(['data' => ['khr_per_usd', 'default_currency']])
            ->assertJsonPath('data.khr_per_usd', fn ($rate) => is_numeric($rate) && $rate > 0);
    }

    public function test_the_money_settings_still_need_a_session(): void
    {
        $this->getJson('/api/v1/settings/money')->assertUnauthorized();
    }
}
