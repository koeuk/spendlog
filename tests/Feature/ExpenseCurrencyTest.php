<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A price can be entered in USD or KHR, but every stored price is USD — the
 * riel amount is converted on the way in at the rate on AppSetting.
 *
 * That invariant is what keeps SUM(price) meaningful for budgets, the
 * over-budget banner, the dashboard and the reports.
 */
class ExpenseCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->applyRole(RoleName::User);

        $this->category = Category::factory()->create();
    }

    private function submit(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post(route('expenses.store'), [
            'item' => ['en' => 'Coffee'],
            'category_uuid' => $this->category->uuid,
            'spent_on' => now()->toDateString(),
            ...$overrides,
        ]);
    }

    public function test_a_usd_price_is_stored_as_typed(): void
    {
        $this->submit(['price' => '4.50', 'currency' => 'USD'])->assertRedirect();

        $this->assertSame('4.50', Expense::sole()->price);
    }

    public function test_a_khr_price_is_converted_to_usd_at_the_configured_rate(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $this->submit(['price' => '20000', 'currency' => 'KHR'])->assertRedirect();

        $this->assertSame('5.00', Expense::sole()->price);
    }

    public function test_conversion_rounds_to_cents_rather_than_truncating(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4100]);

        // 10000 / 4100 = 2.4390…, which truncates to 2.43 and rounds to 2.44.
        $this->submit(['price' => '10000', 'currency' => 'KHR'])->assertRedirect();

        $this->assertSame('2.44', Expense::sole()->price);
    }

    public function test_an_omitted_currency_is_treated_as_usd(): void
    {
        $this->submit(['price' => '12.34'])->assertRedirect();

        $this->assertSame('12.34', Expense::sole()->price);
    }

    public function test_an_unknown_currency_is_rejected(): void
    {
        $this->submit(['price' => '10', 'currency' => 'EUR'])
            ->assertSessionHasErrors('currency');

        $this->assertSame(0, Expense::count());
    }

    public function test_the_rate_falls_back_to_the_default_when_stored_as_zero(): void
    {
        // A hand-edited row must not divide by zero on the next expense entry.
        AppSetting::query()->update(['khr_per_usd' => 0]);

        $this->submit(['price' => '4100', 'currency' => 'KHR'])->assertRedirect();

        $this->assertSame('1.00', Expense::sole()->price);
    }
}
