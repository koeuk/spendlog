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
            // One box per message — see TranslatableInput. A per-locale map is
            // still accepted from older clients, reduced to its en value.
            'warning' => 'Spend less.',
            'advice' => 'Save first.',
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

    public function test_an_admin_saves_the_guidance_and_the_toggle(): void
    {
        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload())
            ->assertRedirect();

        $settings = AppSetting::current();

        $this->assertTrue($settings->spending_guidance_enabled);
        // The columns are still translatable JSON, but the editor writes one
        // value and it lands under the fallback locale.
        $this->assertSame(['en' => 'Spend less.'], $settings->getTranslations('spending_warning'));
        $this->assertSame(['en' => 'Save first.'], $settings->getTranslations('spending_advice'));
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
                'warning' => 'English only.',
                'advice' => '',
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
                'warning' => str_repeat('a', 501),
            ]))
            ->assertSessionHasErrors('warning');
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
            'warning' => '',
            'advice' => '',
        ]));

        $this->assertNull($this->dashboardProps($this->user())['guidance']);
    }

    /**
     * The editor writes one value under the fallback locale, so a Khmer reader
     * gets the English copy via spatie's fallback rather than a blank card.
     */
    public function test_guidance_falls_back_for_a_khmer_reader(): void
    {
        $this->actingAs($this->admin())->post(route('spending.update'), $this->payload());

        app()->setLocale('km');

        $guidance = AppSetting::current()->spendingGuidance();

        $this->assertSame('Spend less.', $guidance['warning']);
        $this->assertSame('Save first.', $guidance['advice']);
    }
}
