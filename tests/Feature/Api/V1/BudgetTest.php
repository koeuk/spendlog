<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TokenAbility;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/budgets')->assertUnauthorized();
    }

    public function test_index_returns_only_the_callers_budgets(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create();
        Budget::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsRead->value]);

        $this->getJson('/api/v1/budgets')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_budget_emits_string_money_and_a_year_month(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->create(['amount' => 300, 'month' => '2026-07-01']);

        Sanctum::actingAs($user, [TokenAbility::BudgetsRead->value]);

        $response = $this->getJson('/api/v1/budgets')->assertOk();

        $this->assertSame('300.00', $response->json('data.0.amount'));
        // 'YYYY-MM' — the day is an implementation detail of the unique index.
        $this->assertSame('2026-07', $response->json('data.0.month'));
    }

    public function test_store_creates_a_category_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->postJson('/api/v1/budgets', [
            'category_uuid' => $category->uuid,
            'month' => '2026-07',
            'amount' => 250,
        ])->assertStatus(201)->assertJsonPath('data.amount', '250.00');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'month' => '2026-07-01',
        ]);
    }

    /**
     * Omitting category_uuid means the overall budget, stored with a null
     * category_id and deduped by the category_key generated column.
     */
    public function test_store_creates_an_overall_budget_when_no_category_is_given(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->postJson('/api/v1/budgets', [
            'month' => '2026-07',
            'amount' => 1000,
        ])->assertStatus(201)->assertJsonPath('data.category', null);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => null,
            'month' => '2026-07-01',
        ]);
    }

    /**
     * The endpoint upserts, so setting the same slot twice must update the row
     * rather than insert a second one — that is what makes it idempotent.
     */
    public function test_setting_the_same_slot_twice_updates_rather_than_duplicates(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->postJson('/api/v1/budgets', [
            'category_uuid' => $category->uuid,
            'month' => '2026-07',
            'amount' => 250,
        ])->assertStatus(201);

        // Second write to the same (category, month) slot: a 200, not a 201.
        $this->postJson('/api/v1/budgets', [
            'category_uuid' => $category->uuid,
            'month' => '2026-07',
            'amount' => 400,
        ])->assertOk()->assertJsonPath('data.amount', '400.00');

        $this->assertSame(1, Budget::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'amount' => 400.00]);
    }

    public function test_two_overall_budgets_for_one_month_do_not_duplicate(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->postJson('/api/v1/budgets', ['month' => '2026-07', 'amount' => 1000])->assertStatus(201);
        $this->postJson('/api/v1/budgets', ['month' => '2026-07', 'amount' => 1200])->assertOk();

        $this->assertSame(1, Budget::where('user_id', $user->id)->count());
    }

    public function test_store_rejects_a_malformed_month(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->postJson('/api/v1/budgets', [
            'month' => '2026-07-16',
            'amount' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('month');
    }

    public function test_a_read_only_token_cannot_set_a_budget(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsRead->value]);

        $this->postJson('/api/v1/budgets', [
            'month' => '2026-07',
            'amount' => 100,
        ])->assertForbidden();
    }

    public function test_a_user_cannot_delete_someone_elses_budget(): void
    {
        $user = User::factory()->create();
        $theirs = Budget::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->deleteJson("/api/v1/budgets/{$theirs->uuid}")->assertForbidden();
        $this->assertDatabaseHas('budgets', ['id' => $theirs->id]);
    }

    public function test_a_user_can_delete_their_own_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::BudgetsWrite->value]);

        $this->deleteJson("/api/v1/budgets/{$budget->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    /**
     * The summary is the figure the Budgets screen actually renders, and it
     * comes from the same service the web side uses.
     */
    public function test_summary_reports_spent_against_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Food']);

        Budget::factory()->for($user)->for($category)->create([
            'amount' => 100,
            'month' => '2026-07-01',
        ]);

        Expense::factory()->for($user)->for($category)->create([
            'price' => 106,
            'spent_on' => '2026-07-10',
        ]);

        Sanctum::actingAs($user, [TokenAbility::BudgetsRead->value]);

        $response = $this->getJson('/api/v1/budgets/summary?month=2026-07')->assertOk();

        $food = collect($response->json('data.categories'))->firstWhere('name', 'Food');

        $this->assertSame(106, $food['percent']);
        $this->assertSame('over', $food['status']);
        // The bar is capped so it cannot overflow its track; percent keeps the truth.
        $this->assertSame(100, $food['bar_percent']);
    }

    public function test_summary_only_counts_the_callers_spend(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Expense::factory()->for($user)->for($category)->create([
            'price' => 10,
            'spent_on' => '2026-07-10',
        ]);
        // Someone else's spend in the same month and category.
        Expense::factory()->for($category)->create([
            'price' => 500,
            'spent_on' => '2026-07-10',
        ]);

        Sanctum::actingAs($user, [TokenAbility::BudgetsRead->value]);

        $response = $this->getJson('/api/v1/budgets/summary?month=2026-07')->assertOk();

        $this->assertSame(10.0, $response->json('data.overall.spent'));
    }
}
