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
        $admin->applyRole(RoleName::Admin);

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

    public function test_filtering_by_name_matches_the_value_not_the_locale_key(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);
        Category::factory()->create(['name' => ['en' => 'Food', 'km' => 'អាហារ']]);
        Category::factory()->create(['name' => ['en' => 'Transport', 'km' => 'ដឹកជញ្ជូន']]);

        Sanctum::actingAs($user, [TokenAbility::CategoriesRead->value]);

        // 'en' is a key in every row's JSON, so a naive LIKE over the raw column
        // matched all of them. It is not part of any name, so it matches none.
        $this->getJson('/api/v1/categories?filter[name]=en')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/categories?filter[name]=Foo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Food');
    }

    public function test_filtering_by_name_finds_a_khmer_value_while_the_api_is_in_english(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);
        Category::factory()->create(['name' => ['en' => 'Food', 'km' => 'អាហារ']]);
        Category::factory()->create(['name' => ['en' => 'Transport', 'km' => 'ដឹកជញ្ជូន']]);

        Sanctum::actingAs($user, [TokenAbility::CategoriesRead->value]);

        // Searching every locale, not just the active one: the caller should not
        // have to know which language a name was typed in.
        $this->getJson('/api/v1/categories?filter[name]=អាហារ')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Food');
    }

    /**
     * Creating is open to everyone by design (see CategoryPolicy::create) — a
     * category gets added mid-flow while logging an expense, and gating that on
     * an admin is how everything ends up filed under "Other".
     *
     * The ability still gates the client, which the sibling test covers: this
     * one asserts only that being a non-admin is not itself a barrier.
     */
    public function test_a_non_admin_can_create_a_category_with_the_ability(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        Sanctum::actingAs($user, [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => ['en' => 'Snacks'],
            'color' => CategoryColor::Red->value,
        ])->assertStatus(201);

        $this->assertSame(1, Category::query()->whereJsonContains('name->en', 'Snacks')->count());
    }

    /**
     * The reported failure, end to end: a mobile client logs in as an ordinary
     * user and uses the inline "add a category while logging" flow the web
     * grants them. This 403'd at the ability middleware — before the policy that
     * would have allowed it was ever consulted — so the same person could do it
     * in a browser and not from their phone.
     *
     * Nothing here is granted by hand: the token is whatever logging in issues.
     */
    public function test_a_default_token_can_create_a_category_inline(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $user->applyRole(RoleName::User);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/v1/categories', [
            'name' => ['en' => 'Snacks'],
            'color' => CategoryColor::Red->value,
        ])->assertStatus(201);

        $this->assertSame(1, Category::query()->whereJsonContains('name->en', 'Snacks')->count());
    }

    /**
     * The counterpart that must keep biting: editing someone else's category is
     * still admin-only, because it changes a row everyone already uses.
     */
    public function test_a_non_admin_cannot_update_a_category_even_with_the_ability(): void
    {
        $category = Category::factory()->create(['name' => ['en' => 'Food']]);

        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        Sanctum::actingAs($user, [TokenAbility::CategoriesWrite->value]);

        $this->patchJson("/api/v1/categories/{$category->uuid}", [
            'name' => ['en' => 'Renamed'],
            'color' => CategoryColor::Red->value,
        ])->assertForbidden();

        $this->assertSame('Food', $category->fresh()->getTranslation('name', 'en'));
    }

    /**
     * And the mirror: an admin whose token lacks the ability is still refused.
     */
    public function test_an_admin_without_the_ability_cannot_create_a_category(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesRead->value]);

        $this->postJson('/api/v1/categories', [
            'name' => ['en' => 'Blocked'],
            'color' => CategoryColor::Red->value,
        ])->assertForbidden();

        $this->assertSame(0, Category::query()->whereJsonContains('name->en', 'Blocked')->count());
    }

    public function test_an_admin_with_the_ability_can_create_a_category(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => ['en' => 'Travel', 'km' => 'ការធ្វើដំណើរ'],
            'color' => CategoryColor::Blue->value,
            'icon' => CategoryIcon::Plane->value,
        ])->assertStatus(201)
            // `name` is resolved for the active locale; the raw map rides along
            // so a client can round trip an edit.
            ->assertJsonPath('data.name', 'Travel')
            ->assertJsonPath('data.name_translations.km', 'ការធ្វើដំណើរ');

        $this->assertSame(1, Category::query()->whereJsonContains('name->en', 'Travel')->count());
    }

    public function test_store_rejects_a_colour_outside_the_enum(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        $this->postJson('/api/v1/categories', [
            'name' => ['en' => 'Bad'],
            'color' => 'chartreuse',
        ])->assertStatus(422)->assertJsonValidationErrors('color');
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Food']);

        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        // Uniqueness is per locale, checked with whereJsonContains rather than
        // Rule::unique, which cannot reach inside a JSON column.
        $this->postJson('/api/v1/categories', [
            'name' => ['en' => 'Food'],
            'color' => CategoryColor::Red->value,
        ])->assertStatus(422)->assertJsonValidationErrors('name.en');
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
            'name' => ['en' => 'Food'],
            'color' => CategoryColor::Green->value,
        ])->assertOk()->assertJsonPath('data.color', CategoryColor::Green->value);
    }

    public function test_store_requires_an_english_name(): void
    {
        Sanctum::actingAs($this->admin(), [TokenAbility::CategoriesWrite->value]);

        // English is the fallback locale, so a Khmer-only category would render
        // as nothing for an English reader.
        $this->postJson('/api/v1/categories', [
            'name' => ['km' => 'ការធ្វើដំណើរ'],
            'color' => CategoryColor::Blue->value,
        ])->assertStatus(422)->assertJsonValidationErrors('name.en');
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
