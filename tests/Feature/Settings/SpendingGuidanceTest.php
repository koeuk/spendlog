<?php

namespace Tests\Feature\Settings;

use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SpendingGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // current() caches forever; a row written by one test would otherwise be
        // read by the next. Same guard ColorsTest uses.
        Cache::flush();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::Admin);

        return $user;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'warning' => ['en' => 'Spend less.', 'km' => 'ចាយតិច។'],
            'advice' => ['en' => 'Save first.', 'km' => 'សន្សំមុន។'],
        ], $overrides);
    }

    // -------------------------------------------------------------- access

    public function test_the_page_is_admin_only(): void
    {
        $this->actingAs($this->user())->get(route('spending.edit'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('spending.edit'))->assertOk();
    }

    public function test_a_non_admin_cannot_save(): void
    {
        $this->actingAs($this->user())
            ->post(route('spending.update'), $this->payload())
            ->assertForbidden();
    }

    // -------------------------------------------------------------- saving

    public function test_an_admin_saves_both_locales_and_the_toggle(): void
    {
        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload())
            ->assertRedirect();

        $settings = AppSetting::current();

        $this->assertTrue($settings->spending_guidance_enabled);
        $this->assertSame('Spend less.', $settings->getTranslation('spending_warning', 'en'));
        $this->assertSame('ចាយតិច។', $settings->getTranslation('spending_warning', 'km'));
        $this->assertSame('Save first.', $settings->getTranslation('spending_advice', 'en'));
        $this->assertSame('សន្សំមុន។', $settings->getTranslation('spending_advice', 'km'));
    }

    /**
     * A blank locale must be dropped, not stored as ''. spatie only falls back to
     * another locale when the key is absent, so a stored '' would show a Khmer
     * reader an empty line instead of the English that was filled in.
     */
    public function test_a_blank_locale_is_dropped_so_the_other_can_fall_back(): void
    {
        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload([
                'warning' => ['en' => 'English only.', 'km' => '   '],
                'advice' => ['en' => '', 'km' => ''],
            ]))
            ->assertRedirect();

        $settings = AppSetting::current();

        // km absent entirely (not stored as ''), so it falls back to en on read.
        $this->assertSame(['en' => 'English only.'], $settings->getTranslations('spending_warning'));
        app()->setLocale('km');
        $this->assertSame('English only.', $settings->spending_warning);

        // advice blank in both → cleared.
        $this->assertSame([], $settings->getTranslations('spending_advice'));
    }

    public function test_over_the_length_limit_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload([
                'warning' => ['en' => str_repeat('a', 501), 'km' => ''],
            ]))
            ->assertSessionHasErrors('warning.en');
    }

    // ------------------------------------------------- dashboard rendering

    /** @return array<string, mixed> */
    private function dashboardProps(User $actor): array
    {
        $response = $this->actingAs($actor)->get(route('dashboard'));
        $response->assertOk();

        preg_match('/data-page="([^"]*)"/', $response->getContent(), $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['props'];
    }

    public function test_the_dashboard_shows_guidance_when_enabled(): void
    {
        $this->actingAs($this->admin())->post(route('spending.update'), $this->payload());

        $guidance = $this->dashboardProps($this->user())['guidance'];

        $this->assertSame('Spend less.', $guidance['warning']);
        $this->assertSame('Save first.', $guidance['advice']);
    }

    public function test_the_dashboard_hides_guidance_when_disabled(): void
    {
        // Text is written, but the master switch is off.
        $this->actingAs($this->admin())->post(route('spending.update'), $this->payload(['enabled' => false]));

        $this->assertNull($this->dashboardProps($this->user())['guidance']);
    }

    public function test_guidance_is_null_when_enabled_but_empty(): void
    {
        $this->actingAs($this->admin())->post(route('spending.update'), $this->payload([
            'enabled' => true,
            'warning' => ['en' => '', 'km' => ''],
            'advice' => ['en' => '', 'km' => ''],
        ]));

        $this->assertNull($this->dashboardProps($this->user())['guidance']);
    }

    public function test_guidance_resolves_to_the_active_locale(): void
    {
        $this->actingAs($this->admin())->post(route('spending.update'), $this->payload());

        app()->setLocale('km');

        $guidance = AppSetting::current()->spendingGuidance();

        $this->assertSame('ចាយតិច។', $guidance['warning']);
        $this->assertSame('សន្សំមុន។', $guidance['advice']);
    }
}
