<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two things the API gained alongside the web: prices may be entered in KHR, and
 * the dashboard's two cards each take their own month.
 *
 * Both go through the same shared code as the web — ExpenseRequest for the
 * conversion, CalendarOptions and CategoryBreakdown for the months — so these
 * tests are mostly guarding that the API keeps reaching for the shared version
 * rather than growing its own.
 */
class CurrencyAndMonthFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-07-15'));

        $this->user = User::factory()->create();
    }

    private function submit(array $payload): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->user, [TokenAbility::ExpensesWrite->value]);

        $category = Category::factory()->create();

        return $this->postJson('/api/v1/expenses', [
            'item' => ['en' => 'Coffee'],
            'category_uuid' => $category->uuid,
            'spent_on' => '2026-07-15',
            ...$payload,
        ]);
    }

    public function test_a_khr_price_is_converted_before_it_is_stored(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $this->submit(['price' => '20000', 'currency' => 'KHR'])->assertCreated();

        $this->assertSame('5.0000', Expense::sole()->price);
    }

    public function test_an_omitted_currency_is_still_usd(): void
    {
        // Every client written before the field existed must keep working.
        $this->submit(['price' => '4.50'])->assertCreated();

        $this->assertSame('4.5000', Expense::sole()->price);
    }

    public function test_an_unknown_currency_is_rejected(): void
    {
        $this->submit(['price' => '10', 'currency' => 'EUR'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_the_dashboard_breakdown_follows_its_own_month(): void
    {
        $this->spend('2026-07-10', 10, 'July thing');
        $this->spend('2026-03-10', 40, 'March thing');

        Sanctum::actingAs($this->user, [TokenAbility::DashboardRead->value]);

        $july = $this->getJson('/api/v1/dashboard')->assertOk();
        $this->assertSame('2026-07', $july->json('data.breakdown_month'));
        $this->assertSame(['July thing'], $this->names($july));

        $march = $this->getJson('/api/v1/dashboard?breakdown_month=2026-03')->assertOk();
        $this->assertSame('2026-03', $march->json('data.breakdown_month'));
        $this->assertSame(['March thing'], $this->names($march));
    }

    public function test_the_dashboard_budget_month_is_separate_from_the_breakdown(): void
    {
        $category = Category::factory()->create();
        $this->user->budgets()->create([
            'category_id' => $category->id,
            'month' => '2026-06-01',
            'amount' => 250,
        ]);

        Sanctum::actingAs($this->user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard?budget_month=2026-06')->assertOk();

        $this->assertSame('2026-06', $response->json('data.budget_month'));
        $this->assertSame('2026-06', $response->json('data.summary.month'));
        // Untouched by the budget month.
        $this->assertSame('2026-07', $response->json('data.breakdown_month'));
        // And the heading month is still the real one.
        $this->assertSame('2026-07', $response->json('data.current_month'));
    }

    public function test_a_junk_month_falls_back_instead_of_rolling_forward(): void
    {
        // 2026-13 matches the YYYY-MM shape and Carbon rolls it to 2027-01 rather
        // than throwing — the API used to hand back next January's figures.
        Sanctum::actingAs($this->user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard?budget_month=2026-13')->assertOk();

        $this->assertSame('2026-07', $response->json('data.budget_month'));
    }

    public function test_the_budget_summary_endpoint_rejects_a_rolled_over_month(): void
    {
        Sanctum::actingAs($this->user, [TokenAbility::BudgetsRead->value]);

        $response = $this->getJson('/api/v1/budgets/summary?month=2026-13')->assertOk();

        $this->assertSame('2026-07', $response->json('data.month'));
    }

    public function test_breakdown_money_is_still_a_string(): void
    {
        $this->spend('2026-07-10', 12.5, 'Coffee');

        Sanctum::actingAs($this->user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        // The shared service deals in floats; this API's money is a string.
        $this->assertSame('12.50', $response->json('data.breakdown.0.spent'));
    }

    private function spend(string $date, float $price, string $categoryName): void
    {
        $category = Category::factory()->create(['name' => ['en' => $categoryName]]);

        $this->user->expenses()->create([
            'category_id' => $category->id,
            'item' => ['en' => $categoryName],
            'price' => $price,
            'spent_on' => $date,
        ]);
    }

    /** @return array<int, string> */
    private function names(\Illuminate\Testing\TestResponse $response): array
    {
        return collect($response->json('data.breakdown'))->pluck('name')->all();
    }
}
