<?php

namespace Tests\Feature\Settings;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ColorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        // current() caches forever, so a row written by one test would otherwise
        // be read by the next.
        Cache::flush();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'button_color' => ButtonColor::Green->value,
            'body_color' => BodyColor::Cream->value,
        ], $overrides);
    }

    public function test_the_page_is_admin_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $this->actingAs($user)->get('/settings/colors')->assertForbidden();
        $this->actingAs($this->admin())->get('/settings/colors')->assertOk();
    }

    public function test_a_non_admin_cannot_change_the_colours(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        $this->actingAs($user)
            ->post('/settings/colors', $this->payload(['button_color' => ButtonColor::Red->value]))
            ->assertForbidden();

        $this->assertSame('#171717', AppSetting::current()->button_color);
    }

    public function test_the_page_ships_the_current_colours_and_both_preset_sets(): void
    {
        $response = $this->actingAs($this->admin())->get('/settings/colors');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('colors.button_color')
                ->has('colors.body_color')
                ->has('body_presets', count(BodyColor::cases()))
                ->has('button_presets', count(ButtonColor::cases()))
                ->where('body_presets.0.value', BodyColor::White->value)
                // The page marks the default rather than making the admin guess.
                ->where('button_presets.0.is_default', true)
            );
    }

    public function test_an_admin_can_set_both_colours(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload())
            ->assertRedirect();

        $settings = AppSetting::current();

        $this->assertSame(ButtonColor::Green->value, $settings->button_color);
        $this->assertSame(BodyColor::Cream->value, $settings->body_color);
    }

    /**
     * A swatch the picker offers but the validator refuses would be a trap, and
     * the two are defined in different places — so this pins them together.
     */
    public function test_every_offered_preset_is_accepted(): void
    {
        foreach (BodyColor::cases() as $preset) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['body_color' => $preset->value]))
                ->assertSessionHasNoErrors();
        }

        foreach (ButtonColor::cases() as $preset) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['button_color' => $preset->value]))
                ->assertSessionHasNoErrors();
        }
    }

    /**
     * The set is the rule now: anything off it is refused, including colours a
     * free picker would once have taken.
     */
    public function test_a_colour_outside_the_offered_set_is_refused(): void
    {
        foreach (['#e8f0ff', '#000000', '#123456'] as $offList) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['body_color' => $offList]))
                ->assertSessionHasErrors('body_color');
        }

        foreach (['#ff0000', '#d92626', 'red', '#fff'] as $offList) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['button_color' => $offList]))
                ->assertSessionHasErrors('button_color');
        }

        $this->assertSame(BodyColor::White->value, AppSetting::current()->body_color);
    }

    /**
     * The ambient wash sits over the page, so it would tint a chosen colour into
     * a gradient. Choosing one turns it off; the default keeps it.
     */
    public function test_the_wash_is_dropped_once_a_background_colour_is_chosen(): void
    {
        $this->assertFalse(AppSetting::current()->plainBackground());

        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['body_color' => BodyColor::Cream->value]));

        $this->assertTrue(AppSetting::current()->plainBackground());
    }

    public function test_the_layout_is_told_whether_to_render_the_wash(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['body_color' => BodyColor::Sage->value]));

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('branding.plain_background', true));
    }

    /**
     * These land inside a CSS custom property, so a loose value is a stylesheet
     * injection rather than merely a wrong colour.
     */
    public function test_it_rejects_anything_that_is_not_a_six_digit_hex(): void
    {
        $bad = [
            'red',
            '#fff',
            '#12345',
            'rgb(0,0,0)',
            '#4b9d5f; } html { display: none }',
            '<script>alert(1)</script>',
            '',
        ];

        foreach ($bad as $value) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['button_color' => $value]))
                ->assertSessionHasErrors('button_color');
        }

        $this->assertSame('#171717', AppSetting::current()->button_color);
    }

    public function test_saving_busts_the_cache_so_the_new_colour_is_served(): void
    {
        // Warm the cache with the old value first — this is what used to serve
        // stale branding until something else happened to clear it.
        $this->assertSame('#171717', AppSetting::current()->button_color);

        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => ButtonColor::Blue->value]))
            ->assertRedirect();

        $this->assertSame(ButtonColor::Blue->value, AppSetting::current()->button_color);
    }

    /**
     * The blade pre-paint script reads this, so a broken shape here is a blank
     * page rather than a wrong colour.
     */
    public function test_css_variables_are_bare_hsl_triplets_with_a_computed_label(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload([
                'button_color' => '#ffffff',
                'body_color' => '#faf8f4',
            ]));

        $vars = AppSetting::current()->cssVariables();

        $this->assertSame('0 0% 100%', $vars['primary']);
        // White button ⇒ dark label, not the default white-on-white.
        $this->assertSame('0 0% 3.9%', $vars['primaryForeground']);
        $this->assertMatchesRegularExpression('/^[\d.]+ [\d.]+% [\d.]+%$/', $vars['background']);
    }

    /**
     * The default must leave --primary alone, not restate its light half.
     *
     * app.css defines --primary as a theme-aware pair: near-black on a white
     * page, near-white on a near-black one. The default #171717 is only the
     * light half, so pinning it across both themes puts a #171717 button on a
     * #0a0a0a page — 1.1:1, invisible. Null here means "keep the stock tokens".
     */
    public function test_the_default_button_colour_does_not_override_the_theme_aware_primary(): void
    {
        $vars = AppSetting::current()->cssVariables();

        $this->assertNull($vars['primary']);
        $this->assertNull($vars['primaryForeground']);
        // The background is a single value and does still apply.
        $this->assertSame('0 0% 100%', $vars['background']); // app.css: --background: 0 0% 100%
    }

    /**
     * A real brand colour is one value and belongs in both themes — that is the
     * whole point of choosing one.
     */
    public function test_a_chosen_button_colour_does_override_primary(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => ButtonColor::Red->value]));

        $vars = AppSetting::current()->cssVariables();

        $this->assertSame('0 64.38% 42.94%', $vars['primary']);
        // Dark red ⇒ a near-white label.
        $this->assertSame('0 0% 98%', $vars['primaryForeground']);
    }

    public function test_the_rendered_page_leaves_primary_alone_at_the_default(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        // The stash is always parked; the override is not.
        $this->assertStringContainsString('--brand-background', $html);
        $this->assertStringContainsString('"primary":null', $html);
    }

    public function test_the_rendered_page_applies_the_colours_before_first_paint(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => '#0000ff']));

        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        // Inline on <html> in a head script, so it beats the stylesheet tokens
        // and lands before the first paint rather than a frame later.
        $this->assertStringContainsString('--primary', $html);
        $this->assertStringContainsString('240 100% 50%', $html);
        $this->assertStringContainsString('--brand-background', $html);
    }
}
