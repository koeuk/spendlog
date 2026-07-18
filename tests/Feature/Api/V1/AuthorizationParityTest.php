<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\TokenAbility;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API enforces the same policies as the web, not just the token's abilities.
 *
 * The two are different things: an ability is baked into a token at issue time,
 * a permission lives on the user and can be revoked afterwards. Checking only the
 * ability means a permission taken away today keeps working through any token
 * handed out yesterday.
 */
class AuthorizationParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** A user holding every ability on their token, minus one permission. */
    private function userWithout(Permission $permission, array $abilities): User
    {
        $user = User::factory()->create();
        $user->revokePermissionTo($permission->value);

        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    public function test_expense_index_honours_a_revoked_view_permission(): void
    {
        $this->userWithout(Permission::ExpensesView, [TokenAbility::ExpensesRead->value]);

        $this->getJson('/api/v1/expenses')->assertForbidden();
    }

    public function test_expense_store_honours_a_revoked_create_permission(): void
    {
        $this->userWithout(Permission::ExpensesCreate, [TokenAbility::ExpensesWrite->value]);
        $category = Category::factory()->create();

        $this->postJson('/api/v1/expenses', [
            'item' => ['en' => 'Coffee'],
            'price' => '4.50',
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Expense::count());
    }

    public function test_expense_store_cannot_create_a_category_without_the_category_permission(): void
    {
        // The hole this closes: expenses:write says nothing about categories, and
        // ExpenseRequest::resolveCategoryId() will firstOrCreate one from a bare
        // name. Someone whose categories.create was revoked could still add rows
        // to a list everybody shares.
        $this->userWithout(Permission::CategoriesCreate, [TokenAbility::ExpensesWrite->value]);

        $this->postJson('/api/v1/expenses', [
            'item' => ['en' => 'Coffee'],
            'price' => '4.50',
            'new_category' => 'Smuggled',
            'spent_on' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Category::where('name->en', 'Smuggled')->count());
        $this->assertSame(0, Expense::count());
    }

    public function test_expense_update_cannot_create_a_category_either(): void
    {
        $user = $this->userWithout(Permission::CategoriesCreate, [TokenAbility::ExpensesWrite->value]);
        $expense = Expense::factory()->for($user)->create();

        $this->patchJson("/api/v1/expenses/{$expense->uuid}", [
            'item' => ['en' => 'Coffee'],
            'price' => '4.50',
            'new_category' => 'Smuggled',
            'spent_on' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Category::where('name->en', 'Smuggled')->count());
    }

    public function test_an_allowed_user_can_still_name_a_category_inline(): void
    {
        // The gate must not break the flow it is guarding — the default role
        // grants categories.create precisely so this works.
        $user = User::factory()->create();
        Sanctum::actingAs($user, [TokenAbility::ExpensesWrite->value]);

        $this->postJson('/api/v1/expenses', [
            'item' => ['en' => 'Coffee'],
            'price' => '4.50',
            'new_category' => 'Allowed',
            'spent_on' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame(1, Category::where('name->en', 'Allowed')->count());
    }

    public function test_dashboard_honours_a_revoked_view_permission(): void
    {
        $this->userWithout(Permission::DashboardView, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_budget_reads_honour_a_revoked_view_permission(): void
    {
        $this->userWithout(Permission::BudgetsView, [TokenAbility::BudgetsRead->value]);

        $this->getJson('/api/v1/budgets')->assertForbidden();
        $this->getJson('/api/v1/budgets/summary')->assertForbidden();
    }

    public function test_budget_store_honours_a_revoked_create_permission(): void
    {
        $this->userWithout(Permission::BudgetsCreate, [TokenAbility::BudgetsWrite->value]);
        $category = Category::factory()->create();

        $this->postJson('/api/v1/budgets', [
            'category_uuid' => $category->uuid,
            'month' => now()->format('Y-m'),
            'amount' => '100.00',
        ])->assertForbidden();

        $this->assertSame(0, Budget::count());
    }
}
