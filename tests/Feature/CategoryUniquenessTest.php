<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Categories are shared by everyone, so two rows for one real category is not a
 * cosmetic problem: the breakdown splits, the picker shows the name twice, and
 * each row can hold its own budget for the same month. The unique index on
 * (user, category, month) cannot see they are the same thing.
 *
 * The Categories page and the inline picker on the expense form used to disagree
 * about what "the same name" meant — whereJsonContains compiles to JSON_CONTAINS
 * and compares under a binary collation, while the inline path lowercased both
 * sides. So "food" saved happily next to "Food", and afterwards the inline
 * dialog refused both.
 */
class CategoryUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    #[DataProvider('caseVariants')]
    public function test_a_name_differing_only_in_case_is_rejected(string $existing, string $attempt): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => ['en' => $existing]]);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => ['en' => $attempt, 'km' => ''],
                'color' => 'slate',
            ])
            ->assertSessionHasErrors('name.en');

        $this->assertSame(1, Category::count());
    }

    public static function caseVariants(): array
    {
        return [
            'lowercased' => ['Food', 'food'],
            'uppercased' => ['Food', 'FOOD'],
            'mixed' => ['Food', 'fOoD'],
        ];
    }

    /** A genuinely different name still gets through. */
    public function test_a_different_name_is_still_accepted(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => ['en' => 'Food']]);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => ['en' => 'Transport', 'km' => ''],
                'color' => 'slate',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Category::count());
    }

    /** Editing a category must not collide with itself. */
    public function test_a_category_can_be_saved_without_renaming_it(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Food']]);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => ['en' => 'Food', 'km' => 'អាហារ'],
                'color' => 'red',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * The other half of the pair. The inline picker refuses a name that already
     * exists and points at the list instead of silently creating a twin — it
     * already compared case-insensitively, which is the behaviour the Categories
     * page has now been brought in line with. Either way, no second row.
     */
    public function test_logging_an_expense_cannot_create_a_case_variant_category(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => ['en' => 'Food']]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'item' => ['en' => 'Lunch'],
                'price' => '12.50',
                'currency' => 'USD',
                'new_category' => 'food',
                'spent_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('new_category');

        $this->assertSame(1, Category::count());
    }

    /**
     * A category that vanishes between the `exists` check and the write must
     * come back as a validation error, not a foreign-key failure. (int) null is
     * 0, which is no category at all: the API returned a 500 where its docblock
     * promises a 422, and the web form flashed a message built from the SQL.
     */
    public function test_an_expense_naming_a_vanished_category_fails_validation(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Food']]);
        $uuid = $category->uuid;

        // Passes `exists`, then goes away before resolveCategoryId looks it up.
        Category::whereKey($category->getKey())->delete();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'item' => ['en' => 'Lunch'],
                'price' => '12.50',
                'currency' => 'USD',
                'category_uuid' => $uuid,
                'spent_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('category_uuid');

        $this->assertSame(0, $user->expenses()->count());
    }
}
