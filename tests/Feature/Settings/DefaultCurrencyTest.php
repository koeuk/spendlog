<?php

namespace Tests\Feature\Settings;

use App\Enums\Currency;
use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The default currency an amount field starts on, set on Settings → Spending.
 *
 * Entry only: every amount is still stored in USD (see App\Enums\Currency), so
 * this changes which way the toggle points, never what lands in the column.
 */
class DefaultCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // current() caches forever; a row written by one test would otherwise be
        // read by the next. Same guard SpendingGuidanceTest uses.
        Cache::flush();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::Admin);

        return $user;
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => false,
            'warning' => [],
            'advice' => [],
        ], $overrides);
    }

    public function test_it_defaults_to_usd(): void
    {
        $this->assertSame(Currency::Usd, AppSetting::current()->defaultCurrency());
    }

    public function test_an_admin_can_switch_it_to_riel(): void
    {
        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload(['default_currency' => 'KHR']))
            ->assertRedirect();

        Cache::flush();

        $this->assertSame(Currency::Khr, AppSetting::current()->defaultCurrency());
    }

    public function test_an_unknown_currency_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload(['default_currency' => 'GBP']))
            ->assertSessionHasErrors('default_currency');
    }

    /**
     * Omitted means "leave it alone" — the guidance copy and the currency share
     * one form, and saving the messages must not reset the currency.
     */
    public function test_omitting_it_leaves_the_stored_value_alone(): void
    {
        AppSetting::current()->update(['default_currency' => Currency::Khr]);
        Cache::flush();

        $this->actingAs($this->admin())
            ->post(route('spending.update'), $this->payload())
            ->assertRedirect();

        Cache::flush();

        $this->assertSame(Currency::Khr, AppSetting::current()->defaultCurrency());
    }

    /**
     * A value written straight to the column, bypassing validation, must not
     * reach the form as null.
     */
    public function test_an_unrecognised_stored_value_falls_back_to_usd(): void
    {
        AppSetting::query()->update(['default_currency' => 'XXX']);
        Cache::flush();

        $this->assertSame(Currency::Usd, AppSetting::current()->defaultCurrency());
    }

    /** Every page that takes an amount needs it, so it is shared globally. */
    public function test_it_is_shared_to_the_front_end(): void
    {
        AppSetting::current()->update(['default_currency' => Currency::Khr]);
        Cache::flush();

        $response = $this->actingAs($this->admin())->get(route('expenses.index'));

        preg_match('/data-page="([^"]*)"/', $response->getContent(), $m);
        $props = json_decode(html_entity_decode($m[1], ENT_QUOTES), true)['props'];

        $this->assertSame('KHR', $props['default_currency']);
    }
}
