<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * category_id null is not "no category" — it is the schema's encoding for the
 * overall budget covering every category. So a category that vanishes between
 * the exists rule and the lookup must fail, not fall through to null.
 */
class BudgetCategoryResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    public function test_a_category_budget_resolves_to_that_category(): void
    {
        $category = Category::factory()->create();

        $request = BudgetRequest::create('/budgets', 'POST', [
            'category_uuid' => $category->uuid,
            'month' => '2026-07',
            'amount' => '250',
        ]);
        $request->setContainer(app())->validateResolved();

        $this->assertSame($category->id, $request->budgetAttributes()['category_id']);
    }

    public function test_an_overall_budget_still_resolves_to_null(): void
    {
        $request = BudgetRequest::create('/budgets', 'POST', [
            'month' => '2026-07',
            'amount' => '250',
        ]);
        $request->setContainer(app())->validateResolved();

        $this->assertNull($request->budgetAttributes()['category_id']);
    }

    public function test_a_category_that_vanishes_after_validation_is_not_written_as_an_overall_budget(): void
    {
        $category = Category::factory()->create();

        $request = BudgetRequest::create('/budgets', 'POST', [
            'category_uuid' => $category->uuid,
            'month' => '2026-07',
            'amount' => '250',
        ]);

        // Passes `exists` while the row is still there...
        $request->setContainer(app())->validateResolved();

        // ...and an admin deletes it before the write lands.
        $category->forceDelete();

        $this->expectException(ValidationException::class);

        $request->budgetAttributes();
    }

    /**
     * The failure that matters, stated as the outcome rather than the mechanism:
     * whatever happens, the row must never become an overall budget.
     */
    public function test_the_vanishing_race_never_creates_an_overall_budget(): void
    {
        $user = $this->user();
        $category = Category::factory()->create();

        $request = BudgetRequest::create('/budgets', 'POST', [
            'category_uuid' => $category->uuid,
            'month' => '2026-07',
            'amount' => '250',
        ]);
        $request->setContainer(app())->validateResolved();

        $category->forceDelete();

        try {
            $user->budgets()->create($request->budgetAttributes());
        } catch (ValidationException) {
            // The point: it refused rather than silently reinterpreting.
        }

        $this->assertSame(
            0,
            Budget::whereNull('category_id')->count(),
            'a category budget was silently written as an overall budget'
        );
    }
}
