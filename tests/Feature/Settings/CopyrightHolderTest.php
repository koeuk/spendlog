<?php

namespace Tests\Feature\Settings;

use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The footer credits a copyright holder, which is not always the product name —
 * an app called SpendLog may be owned by a person or a company.
 */
class CopyrightHolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        // current() caches forever, so a row written by one test would leak.
        Cache::flush();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'SpendLog',
            'copyright_holder' => 'kos koeuk',
        ], $overrides);
    }

    public function test_it_falls_back_to_the_app_name_when_unset(): void
    {
        $settings = AppSetting::current();
        $settings->update(['app_name' => 'SpendLog', 'copyright_holder' => null]);
        Cache::flush();

        $this->assertSame('SpendLog', AppSetting::current()->copyrightHolder());
    }

    public function test_an_admin_sets_a_holder_that_differs_from_the_app_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('branding.update'), $this->payload())
            ->assertRedirect();

        Cache::flush();
        $settings = AppSetting::current();

        // The product keeps its name; the credit is a person.
        $this->assertSame('SpendLog', $settings->app_name);
        $this->assertSame('kos koeuk', $settings->copyrightHolder());
    }

    public function test_blanking_the_holder_restores_the_app_name(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('branding.update'), $this->payload());
        Cache::flush();

        // Submitting an empty box stores null, not '', so the fallback engages.
        $this->actingAs($admin)->post(route('branding.update'), $this->payload([
            'copyright_holder' => '',
        ]));
        Cache::flush();

        $this->assertNull(AppSetting::current()->copyright_holder);
        $this->assertSame('SpendLog', AppSetting::current()->copyrightHolder());
    }

    public function test_the_holder_is_shared_to_every_page_for_the_footer(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('branding.update'), $this->payload());
        Cache::flush();

        $branding = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->viewData('page')['props']['branding'];

        $this->assertSame('kos koeuk', $branding['copyright']);
        $this->assertSame('SpendLog', $branding['name']);
    }

    public function test_a_normal_user_cannot_change_the_holder(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        $this->actingAs($user)
            ->post(route('branding.update'), $this->payload())
            ->assertForbidden();
    }
}
