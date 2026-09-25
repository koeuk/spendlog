<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Currency;
use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An account's own currency and colours: open to everyone, overriding the
 * admin's app-wide values for that account alone.
 */
class PreferenceTest extends TestCase
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

    public function test_a_plain_user_can_read_and_set_their_preferences(): void
    {
        $user = $this->user();
        // No ability at all: preferences are cosmetic, like the photo.
        Sanctum::actingAs($user, []);

        $this->getJson('/api/v1/preferences')
            ->assertOk()
            ->assertJsonPath('data.currency', null)
            ->assertJsonPath('data.button_color', null)
            ->assertJsonStructure(['data' => ['currency', 'button_color', 'body_color', 'default_currency', 'button_presets', 'body_presets']]);

        $this->putJson('/api/v1/preferences', ['currency' => 'KHR', 'button_color' => '#2F6F43', 'body_color' => '#faf8f4'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'KHR')
            ->assertJsonPath('data.default_currency', 'KHR')
            // Stored lower-case, however it was sent.
            ->assertJsonPath('data.button_color', '#2f6f43')
            ->assertJsonPath('data.body_color', '#faf8f4');

        $this->assertSame(Currency::Khr, $user->fresh()->preferred_currency);
    }

    public function test_only_the_fields_sent_change_and_null_follows_the_app_again(): void
    {
        $user = $this->user();
        $user->update(['preferred_currency' => 'KHR', 'button_color' => '#2f6f43']);
        Sanctum::actingAs($user, []);

        $this->putJson('/api/v1/preferences', ['button_color' => null])
            ->assertOk()
            ->assertJsonPath('data.button_color', null)
            ->assertJsonPath('data.currency', 'KHR');
    }

    public function test_the_colour_rules_are_the_admin_forms(): void
    {
        Sanctum::actingAs($this->user(), []);

        // A mid-tone no label can be read on, and a background off the list.
        $this->putJson('/api/v1/preferences', ['button_color' => '#ad661f', 'body_color' => '#123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_color', 'body_color']);

        $this->putJson('/api/v1/preferences', ['currency' => 'EUR'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_the_money_settings_start_on_the_accounts_own_currency(): void
    {
        AppSetting::current()->update(['default_currency' => 'USD']);
        $user = $this->user();
        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/settings/money')->assertJsonPath('data.default_currency', 'USD');

        $user->update(['preferred_currency' => 'KHR']);

        $this->getJson('/api/v1/settings/money')->assertJsonPath('data.default_currency', 'KHR');
    }

    public function test_me_carries_the_preferences(): void
    {
        $user = $this->user();
        $user->update(['body_color' => '#faf8f4']);
        Sanctum::actingAs($user, []);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.preferences.body_color', '#faf8f4')
            ->assertJsonPath('data.preferences.currency', null);
    }

    public function test_preferences_need_a_session(): void
    {
        $this->getJson('/api/v1/preferences')->assertUnauthorized();
        $this->putJson('/api/v1/preferences', ['currency' => 'KHR'])->assertUnauthorized();
    }
}
