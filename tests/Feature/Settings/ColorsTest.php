<?php

namespace Tests\Feature\Settings;

use App\Enums\BodyColor;
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
            'button_color' => '#4b9d5f',
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
            ->post('/settings/colors', $this->payload(['button_color' => '#ff0000']))
            ->assertForbidden();

        $this->assertSame('#171717', AppSetting::current()->button_color);
    }

    public function test_the_page_ships_the_current_colours_and_the_five_presets(): void
    {
        $response = $this->actingAs($this->admin())->get('/settings/colors');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('colors.button_color')
                ->has('colors.body_color')
                ->has('body_presets', 5)
                ->where('body_presets.0.value', BodyColor::White->value)
            );
    }

    public function test_an_admin_can_set_both_colours(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload())
            ->assertRedirect();

        $settings = AppSetting::current();

        $this->assertSame('#4b9d5f', $settings->button_color);
        $this->assertSame(BodyColor::Cream->value, $settings->body_color);
    }

    public function test_a_custom_body_colour_outside_the_presets_is_accepted(): void
    {
        // "Flexible" is the point: the five presets are a shortcut, not a fence.
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['body_color' => '#123456']))
            ->assertRedirect();

        $this->assertSame('#123456', AppSetting::current()->body_color);
    }

    public function test_hex_is_normalised_to_lower_case(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => '#4B9D5F']))
            ->assertRedirect();

        // One canonical form in the column, so comparing against a preset stays
        // a plain string match.
        $this->assertSame('#4b9d5f', AppSetting::current()->button_color);
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
            ->post('/settings/colors', $this->payload(['button_color' => '#0000ff']))
            ->assertRedirect();

        $this->assertSame('#0000ff', AppSetting::current()->button_color);
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
            ->post('/settings/colors', $this->payload(['button_color' => '#b42727']));

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
