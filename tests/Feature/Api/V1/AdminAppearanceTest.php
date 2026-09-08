<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Branding and colours over the API — the same rules as the web Settings
 * pages, behind the same double gate as the spending settings.
 */
class AdminAppearanceTest extends TestCase
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

    // --------------------------------------------------------------- branding

    public function test_an_admin_reads_branding(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $this->getJson('/api/v1/admin/settings/branding')
            ->assertOk()
            ->assertJsonStructure(['data' => ['app_name', 'copyright_holder', 'logo', 'favicon']]);
    }

    public function test_an_admin_updates_the_name_and_uploads_marks(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $response = $this->post('/api/v1/admin/settings/branding', [
            'app_name' => 'Ledger',
            'copyright_holder' => '',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
            'favicon' => UploadedFile::fake()->image('mark.png', 200, 200),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('Ledger', $response->json('data.app_name'));
        $this->assertNull($response->json('data.copyright_holder'));
        $this->assertStringContainsString('/storage/branding/', $response->json('data.logo'));
        $this->assertStringContainsString('/storage/branding/', $response->json('data.favicon'));

        $settings = AppSetting::current()->fresh();
        Storage::disk('public')->assertExists($settings->logo_path);
        Storage::disk('public')->assertExists($settings->favicon_path);
    }

    public function test_a_remove_flag_clears_an_image_and_deletes_the_file(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $this->post('/api/v1/admin/settings/branding', [
            'app_name' => 'Ledger',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = AppSetting::current()->fresh()->logo_path;

        $this->post('/api/v1/admin/settings/branding', [
            'app_name' => 'Ledger',
            'remove_logo' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.logo', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_svg_logo_is_refused(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $this->post('/api/v1/admin/settings/branding', [
            'app_name' => 'Ledger',
            'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    }

    public function test_a_regular_user_cannot_touch_branding(): void
    {
        Sanctum::actingAs($this->user(), [TokenAbility::SettingsWrite->value]);

        $this->getJson('/api/v1/admin/settings/branding')->assertForbidden();
    }

    // ---------------------------------------------------------------- colours

    public function test_an_admin_reads_colours_with_presets(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $response = $this->getJson('/api/v1/admin/settings/colors')->assertOk();

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $response->json('data.button_color'));
        $this->assertNotEmpty($response->json('data.button_presets'));
        $this->assertNotEmpty($response->json('data.body_presets'));
        $this->assertArrayHasKey('is_default', $response->json('data.button_presets.0'));
    }

    public function test_an_admin_updates_the_colours(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $this->putJson('/api/v1/admin/settings/colors', [
            'button_color' => '#1d4ed8',
            'body_color' => '#faf8f4',
        ])
            ->assertOk()
            ->assertJsonPath('data.button_color', '#1d4ed8')
            ->assertJsonPath('data.body_color', '#faf8f4');

        $this->assertSame('#1d4ed8', AppSetting::current()->fresh()->button_color);
    }

    public function test_a_body_colour_off_the_list_is_rejected(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::SettingsWrite->value]);

        $this->putJson('/api/v1/admin/settings/colors', [
            'button_color' => '#1d4ed8',
            'body_color' => '#123456',
        ])->assertStatus(422)->assertJsonValidationErrors('body_color');
    }

    public function test_a_token_without_the_ability_is_refused(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/admin/settings/colors')->assertForbidden();
    }
}
