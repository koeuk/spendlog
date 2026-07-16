<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    }

    public function test_any_user_can_read_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Food']);

        Sanctum::actingAs($user, [TokenAbility::CategoriesRead->value]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Food')
            ->assertJsonPath('data.0.expenses_count', 0);
    }

    /**
     * The ability gates the client; the policy gates the user. This checks the
     * policy still bites even when the token would allow the call.
     */
    public function test_a_non_admin_cannot_create_a_category_even_with_the_ability(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

        Sanctum::actingAs($user, [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => 'Sneaky',
            'color' => CategoryColor::Red->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Sneaky']);
    }

    /**
     * And the mirror: an admin whose token lacks the ability is still refused.
     */
    public function test_an_admin_without_the_ability_cannot_create_a_category(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesRead->value]);

        $this->postJson('/api/v1/categories', [
            'name' => 'Blocked',
            'color' => CategoryColor::Red->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Blocked']);
    }

    public function test_an_admin_with_the_ability_can_create_a_category(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => 'Travel',
            'color' => CategoryColor::Blue->value,
            'icon' => CategoryIcon::Plane->value,
        ])->assertStatus(201)->assertJsonPath('data.name', 'Travel');

        $this->assertDatabaseHas('categories', ['name' => 'Travel']);
    }

    public function test_store_rejects_a_colour_outside_the_enum(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => 'Bad',
            'color' => 'chartreuse',
        ])->assertStatus(422)->assertJsonValidationErrors('color');
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Food']);

        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => 'Food',
            'color' => CategoryColor::Red->value,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    /**
     * The unique rule must ignore the row being edited, or renaming a category
     * to its own name would fail.
     */
    public function test_a_category_can_keep_its_own_name_on_update(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);

        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->patchJson("/api/v1/categories/{$category->uuid}", [
            'name' => 'Food',
            'color' => CategoryColor::Green->value,
        ])->assertOk()->assertJsonPath('data.color', CategoryColor::Green->value);
    }

    public function test_an_unused_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->deleteJson("/api/v1/categories/{$category->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * The foreign keys restrict on delete. That must surface as a 409 with a
     * readable message, not a 500 full of SQL.
     */
    public function test_deleting_a_category_with_expenses_returns_409(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);
        Expense::factory()->for($category)->create();

        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $response = $this->deleteJson("/api/v1/categories/{$category->uuid}");

        $response->assertStatus(409)->assertJsonPath('message', '"Food" is still in use and cannot be deleted.');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_deleting_a_category_with_budgets_returns_409(): void
    {
        $category = Category::factory()->create();
        Budget::factory()->for($category)->create();

        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->deleteJson("/api/v1/categories/{$category->uuid}")->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_a_non_uuid_route_key_404s(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesRead->value]);

        // The internal id must not be addressable from outside.
        $category = Category::factory()->create();

        $this->getJson("/api/v1/categories/{$category->id}")->assertNotFound();
    }
}
