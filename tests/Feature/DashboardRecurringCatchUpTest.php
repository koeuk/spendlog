<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\Income;
use App\Models\RecurringRule;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opening the dashboard materialises whatever a recurring rule owes.
 *
 * routes/console.php states the design: the nightly command writes the rows,
 * "the dashboard catches up too, so a missed night is not a missed row". The
 * API dashboard did that; the web one did not, so the same account saw today's
 * rent from its phone and not from the browser — and nothing at all if the
 * scheduler was not running, which is the normal state of a container whose
 * only process is `php artisan serve`.
 */
class DashboardRecurringCatchUpTest extends TestCase
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

    public function test_the_web_dashboard_writes_a_due_expense_rule(): void
    {
        $user = $this->user();
        $rule = RecurringRule::factory()->create(['user_id' => $user->id, 'amount' => 42.00]);

        $this->assertSame(0, Expense::where('recurring_rule_id', $rule->id)->count());

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(1, Expense::where('recurring_rule_id', $rule->id)->count());
    }

    public function test_the_web_dashboard_writes_a_due_income_rule(): void
    {
        $user = $this->user();
        $rule = RecurringRule::factory()->income()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(1, Income::where('recurring_rule_id', $rule->id)->count());
    }

    public function test_the_caught_up_expense_counts_towards_this_months_total(): void
    {
        $user = $this->user();
        RecurringRule::factory()->create(['user_id' => $user->id, 'amount' => 42.00]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $spent = (float) $response->viewData('page')['props']['summary']['overall']['spent'];

        $this->assertSame(42.0, $spent);
    }

    public function test_opening_the_dashboard_twice_does_not_write_twice(): void
    {
        $user = $this->user();
        $rule = RecurringRule::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame(1, Expense::where('recurring_rule_id', $rule->id)->count());
    }

    public function test_another_accounts_rule_is_left_alone(): void
    {
        $mine = $this->user();
        $theirs = $this->user();
        $rule = RecurringRule::factory()->create(['user_id' => $theirs->id]);

        $this->actingAs($mine)->get('/dashboard')->assertOk();

        $this->assertSame(0, Expense::where('recurring_rule_id', $rule->id)->count());
    }
}
