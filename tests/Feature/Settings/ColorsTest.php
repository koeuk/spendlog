<?php

namespace Tests\Feature\Settings;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\Color;
use App\Support\Palette;
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
        $admin->applyRole(RoleName::Admin);

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
        $user->applyRole(RoleName::User);

        $this->actingAs($user)->get('/settings/colors')->assertForbidden();
        $this->actingAs($this->admin())->get('/settings/colors')->assertOk();
    }

    public function test_a_non_admin_cannot_change_the_colours(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

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
                // Silver leads the row — it is the default.
                ->where('body_presets.0.value', BodyColor::Silver->value)
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
     * Eleven offered backgrounds, and every one has to derive a theme whose text
     * is readable — the swatch is the promise. Silver derives with white cards
     * (see Palette::from's whiteCards path); supports() checks the derived card,
     * so its readability is covered here like the rest.
     */
    public function test_every_background_preset_derives_a_readable_theme(): void
    {
        $this->assertCount(11, BodyColor::cases());

        foreach (BodyColor::cases() as $preset) {
            $this->assertTrue(
                Palette::supports($preset->value),
                "{$preset->name} ({$preset->value}) cannot carry a readable theme",
            );
        }
    }

    /**
     * The background is the list — every token in the theme is derived from it,
     * so an unusable value breaks the page rather than one control.
     */
    public function test_a_background_outside_the_offered_set_is_refused(): void
    {
        foreach (['#e8f0ff', '#000000', '#123456'] as $offList) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['body_color' => $offList]))
                ->assertSessionHasErrors('body_color');
        }

        $this->assertSame(BodyColor::Silver->value, AppSetting::current()->body_color);
    }

    /**
     * The button is not: its swatches are a shortcut, so a brand colour that is
     * not on the list is still fair game.
     */
    public function test_a_custom_button_colour_outside_the_presets_is_accepted(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => '#004d7a']))
            ->assertSessionHasNoErrors();

        $this->assertSame('#004d7a', AppSetting::current()->button_color);
    }

    /**
     * A greyish mid-tone still labels fine (#928b9c is 6.03:1) — the guard is
     * about readability, and this one is readable.
     *
     * This is the value that exposed the picker-never-saves bug: the page showed
     * it in the field while the theme kept the old colour, because the commit
     * handler compared against the draft the picker had already written to.
     */
    public function test_a_muted_custom_button_colour_is_accepted(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => '#928b9c']))
            ->assertSessionHasNoErrors();

        $this->assertSame('#928b9c', AppSetting::current()->button_color);
    }

    /**
     * ...but only if a label can sit on it. A label is near-black or near-white,
     * so a fill contrasting with neither cannot be labelled at all — #ad661f tops
     * out at 4.43:1 against *both* ends. That is a refusal, not a preference: no
     * choice of label rescues it.
     */
    public function test_a_button_colour_that_can_carry_no_label_is_refused(): void
    {
        // Measured, not guessed. A saturated red like #d92626 looks like it
        // belongs here and does not — it labels fine at 4.73:1. The band that
        // fails is narrow, so these are the values that actually sit in it.
        foreach (['#d92680', '#ad661f', '#767676'] as $unlabellable) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['button_color' => $unlabellable]))
                ->assertSessionHasErrors('button_color');
        }

        $this->assertSame('#171717', AppSetting::current()->button_color);
    }

    public function test_a_saturated_colour_that_can_carry_a_label_is_accepted(): void
    {
        // The guard is about readability, not saturation — it must not refuse a
        // strong brand colour just for being strong.
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => '#d92626']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_malformed_button_colour_is_refused(): void
    {
        foreach (['red', '#fff', '#12345', 'rgb(0,0,0)', '#4b9d5f; } html { display: none }', ''] as $bad) {
            $this->actingAs($this->admin())
                ->post('/settings/colors', $this->payload(['button_color' => $bad]))
                ->assertSessionHasErrors('button_color');
        }
    }

    /**
     * The ambient wash sits over the page, so it would tint a colour into a
     * gradient — so it belongs to the White background alone. The Silver default
     * and every other choice render flat.
     */
    public function test_the_wash_belongs_to_the_white_background(): void
    {
        // The default is Silver, which is flat.
        $this->assertTrue(AppSetting::current()->plainBackground());

        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['body_color' => BodyColor::White->value]));

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
    public function test_css_variables_carry_a_derived_palette_with_a_computed_label(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload([
                'button_color' => ButtonColor::Amber->value,
                'body_color' => BodyColor::Sage->value,
            ]));

        $vars = AppSetting::current()->cssVariables();

        // Dark amber ⇒ a near-white label, computed rather than chosen.
        $this->assertSame('0 0% 98%', $vars['primaryForeground']);

        // The whole theme, not just the page — that is the point of the palette.
        // Edges are the exception: app.css keeps --border neutral on every theme.
        foreach (['card', 'muted-foreground', 'foreground'] as $token) {
            $this->assertArrayHasKey($token, $vars['palette']);
        }

        $this->assertArrayNotHasKey('border', $vars['palette']);

        foreach ($vars['palette'] as $token => $triplet) {
            $this->assertMatchesRegularExpression('/^[\d.]+ [\d.]+% [\d.]+%$/', $triplet, $token);
        }
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

        // The underived, stock-token theme is now the White background — the
        // Silver default derives its own palette. The default button leaves
        // White's stock tokens alone all the same.
        $white = AppSetting::current();
        $white->body_color = BodyColor::White->value;
        $this->assertNull($white->cssVariables()['palette']);
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

    /**
     * The Silver default is not the stock theme: it derives a flat silver page
     * with pure white cards, and flags the opaque-card class that renders them.
     */
    public function test_the_silver_default_derives_white_cards(): void
    {
        $vars = AppSetting::current()->cssVariables();

        $this->assertNotNull($vars['palette']);
        $this->assertSame('0% 100%', substr($vars['palette']['card'], strpos($vars['palette']['card'], ' ') + 1));
        $this->assertTrue($vars['solidCards']);
    }

    public function test_the_white_background_leaves_the_stock_theme_alone(): void
    {
        // White is now the stock-theme choice — the Silver default derives a
        // palette, so the untouched case is reached by picking White. The button
        // stays at its default too, or it would write a --primary of its own.
        $this->actingAs($this->admin())->post('/settings/colors', $this->payload([
            'button_color' => '#171717',
            'body_color' => BodyColor::White->value,
        ]));

        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        // Nothing to override: the stock tokens already are this theme.
        $this->assertStringContainsString('"primary":null', $html);
        $this->assertStringContainsString('"palette":null', $html);
    }

    public function test_the_rendered_page_carries_the_palette_once_a_background_is_chosen(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['body_color' => BodyColor::Sage->value]));

        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        // Applied inline before first paint, so the page never flashes the stock
        // palette on the way to the admin's.
        $this->assertStringContainsString('__brandPalette', $html);
        $this->assertStringContainsString('"card"', $html);
    }

    public function test_the_rendered_page_applies_the_colours_before_first_paint(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/colors', $this->payload(['button_color' => ButtonColor::Blue->value]));

        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        // Inline on <html> in a head script, so it beats the stylesheet tokens
        // and lands before the first paint rather than a frame later.
        $this->assertStringContainsString('--primary', $html);
        $this->assertStringContainsString(Color::toHslTriplet(ButtonColor::Blue->value), $html);
    }
}
