<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/expenses')->assertUnauthorized();
    }

    public function test_index_returns_only_the_callers_expenses(): void
    {
        $user = User::factory()->create();
        $mine = Expense::factory()->for($user)->create(['item' => 'My coffee']);
        Expense::factory()->create(['item' => 'Someone elses lunch']);

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $response = $this->getJson('/api/v1/expenses');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($mine->uuid, $response->json('data.0.uuid'));
    }

    public function test_index_emits_uuids_and_string_money_and_no_internal_id(): void
    {
        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['price' => 12.5]);

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $response = $this->getJson('/api/v1/expenses')->assertOk();

        // "12.50", not 12.5 — the trailing zero is the whole reason money is a
        // string in this API.
        $this->assertSame('12.50', $response->json('data.0.price'));
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
        $this->assertArrayNotHasKey('id', $response->json('data.0.category'));
    }

    public function test_a_read_only_token_cannot_write(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $this->postJson('/api/v1/expenses', [
            'item' => 'Coffee',
            'price' => 4.5,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_store_creates_an_expense_owned_by_the_token_holder(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesWrite->value]);

        $response = $this->postJson('/api/v1/expenses', [
            'item' => 'Coffee',
            'price' => 4.5,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
        ]);

        $response->assertStatus(201)->assertJsonPath('data.item', 'Coffee');

        $this->assertDatabaseHas('expenses', [
            'item' => 'Coffee',
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
    }

    /**
     * user_id is not fillable, so a payload naming someone else must be ignored
     * rather than honoured.
     */
    public function test_store_ignores_a_user_id_in_the_payload(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesWrite->value]);

        $this->postJson('/api/v1/expenses', [
            'item' => 'Coffee',
            'price' => 4.5,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
            'user_id' => $victim->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('expenses', ['item' => 'Coffee', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('expenses', ['item' => 'Coffee', 'user_id' => $victim->id]);
    }

    public function test_store_rejects_a_future_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesWrite->value]);

        $this->postJson('/api/v1/expenses', [
            'item' => 'Coffee',
            'price' => 4.5,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('spent_on');
    }

    public function test_a_user_cannot_update_someone_elses_expense(): void
    {
        $user = User::factory()->create();
        $theirs = Expense::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesWrite->value]);

        $this->patchJson("/api/v1/expenses/{$theirs->uuid}", [
            'item' => 'Hijacked',
            'price' => 1,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_a_user_cannot_delete_someone_elses_expense(): void
    {
        $user = User::factory()->create();
        $theirs = Expense::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesWrite->value]);

        $this->deleteJson("/api/v1/expenses/{$theirs->uuid}")->assertForbidden();
        $this->assertDatabaseHas('expenses', ['id' => $theirs->id]);
    }

    public function test_a_user_cannot_view_someone_elses_expense(): void
    {
        $user = User::factory()->create();
        $theirs = Expense::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $this->getJson("/api/v1/expenses/{$theirs->uuid}")->assertForbidden();
    }

    public function test_an_admin_can_update_anyones_expense(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $theirs = Expense::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($admin, [TokenAbility::ExpensesWrite->value]);

        $this->patchJson("/api/v1/expenses/{$theirs->uuid}", [
            'item' => 'Corrected',
            'price' => 9.99,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('data.item', 'Corrected');
    }

    public function test_scope_all_is_ignored_for_a_non_admin(): void
    {
        $user = User::factory()->create();
        Expense::factory()->for($user)->create();
        Expense::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $this->getJson('/api/v1/expenses?scope=all')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_admin_can_view_everyones_expenses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Expense::factory()->for($admin)->create();
        Expense::factory()->create();

        Sanctum::actingAs($admin, [TokenAbility::ExpensesRead->value]);

        $this->getJson('/api/v1/expenses?scope=all')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['owner' => ['uuid', 'name']]]]);
    }

    /**
     * The user filter only exists while an admin views everyone, so a non-admin
     * asking for it must be rejected rather than quietly ignored.
     */
    public function test_a_non_admin_cannot_filter_by_user(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $this->getJson("/api/v1/expenses?filter[user]={$victim->uuid}")
            ->assertStatus(400);
    }

    public function test_owner_is_hidden_from_a_normal_listing(): void
    {
        $user = User::factory()->create();
        Expense::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $response = $this->getJson('/api/v1/expenses')->assertOk();

        $this->assertArrayNotHasKey('owner', $response->json('data.0'));
    }

    public function test_per_page_is_clamped(): void
    {
        $user = User::factory()->create();
        Expense::factory()->for($user)->count(3)->create();

        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        // Asking for the whole table must not be honoured.
        $this->getJson('/api/v1/expenses?per_page=99999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }
}
