<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\RecurringRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so "today" is not whatever the clock says when the suite runs.
        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function expensePayload(Category $category, array $overrides = []): array
    {
        return [
            'kind' => 'expense',
            'title' => 'Rent',
            'amount' => '450',
            'category_uuid' => $category->uuid,
            'frequency' => 'monthly',
            'starts_on' => '2026-09-08',
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function incomePayload(array $overrides = []): array
    {
        return [
            'kind' => 'income',
            'title' => 'Salary',
            'amount' => '1200',
            'frequency' => 'monthly',
            'starts_on' => '2026-09-08',
            ...$overrides,
        ];
    }

    /** @return array<int, string> */
    private function readWrite(): array
    {
        return [TokenAbility::RecurringRead->value, TokenAbility::RecurringWrite->value];
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/recurring')->assertUnauthorized();
    }

    public function test_creating_an_expense_rule_starting_today_writes_todays_expense_at_once(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [...$this->readWrite(), TokenAbility::ExpensesRead->value]);

        $response = $this->postJson('/api/v1/recurring', $this->expensePayload($category))
            ->assertCreated()
            ->assertJsonStructure(['data' => [
                'uuid', 'kind', 'title', 'amount', 'category', 'frequency', 'starts_on', 'ends_on',
                'next_run_on', 'last_run_on', 'active', 'note', 'created_at', 'updated_at',
            ]])
            ->assertJsonPath('data.kind', 'expense')
            ->assertJsonPath('data.amount', '450.00')
            ->assertJsonPath('data.category.uuid', $category->uuid)
            ->assertJsonPath('data.last_run_on', '2026-09-08')
            ->assertJsonPath('data.next_run_on', '2026-10-08')
            ->assertJsonPath('data.active', true);

        $this->assertArrayNotHasKey('id', $response->json('data'));

        $rule = RecurringRule::sole();
        $expense = Expense::sole();

        $this->assertSame($user->id, $expense->user_id);
        $this->assertSame($rule->id, $expense->recurring_rule_id);
        $this->assertSame($category->id, $expense->category_id);
        $this->assertSame('Rent', $expense->item);
        $this->assertSame('2026-09-08', $expense->spent_on->toDateString());
        $this->assertSame('450.0000', $expense->price);

        // The row is badged for the app, and stays an ordinary expense to
        // every other endpoint.
        $this->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recurring', true);
    }

    public function test_creating_an_income_rule_writes_income_with_the_rules_note(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [...$this->readWrite(), TokenAbility::IncomesRead->value]);

        $this->postJson('/api/v1/recurring', $this->incomePayload(['note' => 'Monthly pay']))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'income')
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.note', 'Monthly pay')
            ->assertJsonPath('data.last_run_on', '2026-09-08');

        $income = Income::sole();

        $this->assertSame($user->id, $income->user_id);
        $this->assertSame(RecurringRule::sole()->id, $income->recurring_rule_id);
        $this->assertSame('Salary', $income->source);
        $this->assertSame('Monthly pay', $income->note);
        $this->assertSame('2026-09-08', $income->received_on->toDateString());

        $this->getJson('/api/v1/incomes')
            ->assertOk()
            ->assertJsonPath('data.0.recurring', true);
    }

    public function test_a_monthly_rule_keeps_its_day_and_clamps_short_months(): void
    {
        // Mid-April: Jan 31, Feb 28 and Mar 31 are due; Apr 30 is not yet.
        Carbon::setTestNow('2026-04-15 10:00:00');

        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $this->postJson('/api/v1/recurring', $this->expensePayload($category, ['starts_on' => '2026-01-31']))
            ->assertCreated()
            ->assertJsonPath('data.last_run_on', '2026-03-31')
            ->assertJsonPath('data.next_run_on', '2026-04-30');

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31'],
            Expense::query()->orderBy('spent_on')->pluck('spent_on')->map->toDateString()->all(),
        );
    }

    public function test_catch_up_writes_every_missed_occurrence_and_never_twice(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, $this->readWrite());

        // Ten days ago, daily: eleven rows including today.
        $this->postJson('/api/v1/recurring', $this->expensePayload($category, [
            'frequency' => 'daily',
            'starts_on' => '2026-08-29',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.next_run_on', '2026-09-09');

        $this->assertSame(11, Expense::count());

        $rule = RecurringRule::sole();

        // Nothing is due, so a second run writes nothing.
        $this->assertSame(0, app(RecurringRunner::class)->run($rule));

        // And a cursor moved back onto days already written skips them: the
        // dates on disk, not the cursor, decide what exists.
        $rule->forceFill(['next_run_on' => '2026-09-01'])->save();

        $this->assertSame(0, app(RecurringRunner::class)->run($rule));
        $this->assertSame(11, Expense::count());
        $this->assertSame('2026-09-09', $rule->next_run_on->toDateString());
    }

    public function test_catch_up_is_capped_per_run_and_resumes_next_time(): void
    {
        $rule = RecurringRule::factory()->create([
            'frequency' => 'daily',
            'starts_on' => '2025-01-01',
            'next_run_on' => '2025-01-01',
        ]);

        $runner = app(RecurringRunner::class);

        $this->assertSame(RecurringRunner::MAX_PER_RUN, $runner->run($rule));
        $this->assertSame('2026-02-05', $rule->next_run_on->toDateString());
        $this->assertTrue($rule->active);

        // The next run carries on from where the cap stopped.
        $this->assertSame(216, $runner->run($rule));
        $this->assertSame('2026-09-09', $rule->next_run_on->toDateString());
        $this->assertSame(616, Expense::count());
    }

    public function test_a_rule_past_its_end_date_is_switched_off(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $this->postJson('/api/v1/recurring', $this->expensePayload($category, [
            'frequency' => 'weekly',
            'starts_on' => '2026-08-20',
            'ends_on' => '2026-09-05',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.last_run_on', '2026-09-03')
            ->assertJsonPath('data.next_run_on', '2026-09-10');

        // Aug 20, 27, Sep 3 — and not Sep 10, which is past the end.
        $this->assertSame(3, Expense::count());

        // Off means off: the nightly run leaves it alone.
        $this->artisan('spendlog:run-recurring')->assertSuccessful();
        $this->assertSame(3, Expense::count());
    }

    public function test_the_dashboard_materialises_a_due_rule_before_computing_totals(): void
    {
        $user = User::factory()->create();
        RecurringRule::factory()->for($user)->create([
            'amount' => 12.5,
            'starts_on' => '2026-09-08',
            'next_run_on' => '2026-09-08',
        ]);
        // Someone else's due rule is not this caller's business.
        RecurringRule::factory()->create(['starts_on' => '2026-09-08', 'next_run_on' => '2026-09-08']);

        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today.total', '12.50')
            ->assertJsonPath('data.recent.0.recurring', true);

        $this->assertSame(1, Expense::where('user_id', $user->id)->count());
        $this->assertSame(1, Expense::count());
    }

    public function test_the_console_command_runs_every_users_rules(): void
    {
        RecurringRule::factory()->create(['starts_on' => '2026-09-07', 'next_run_on' => '2026-09-07']);
        RecurringRule::factory()->income()->create(['starts_on' => '2026-09-08', 'next_run_on' => '2026-09-08']);
        // Not due yet, and switched off: neither writes.
        RecurringRule::factory()->create(['starts_on' => '2026-09-09', 'next_run_on' => '2026-09-09']);
        RecurringRule::factory()->create(['starts_on' => '2026-09-01', 'next_run_on' => '2026-09-01', 'active' => false]);

        $this->artisan('spendlog:run-recurring')
            ->expectsOutputToContain('Created 2 recurring row(s).')
            ->assertSuccessful();

        $this->assertSame(1, Expense::count());
        $this->assertSame(1, Income::count());

        // Idempotent: the same night twice writes nothing more.
        $this->artisan('spendlog:run-recurring')
            ->expectsOutputToContain('Created 0 recurring row(s).')
            ->assertSuccessful();

        $this->assertSame(2, Expense::count() + Income::count());
    }

    public function test_index_lists_active_first_then_by_next_occurrence_and_filters_by_kind(): void
    {
        $user = User::factory()->create();
        $later = RecurringRule::factory()->for($user)->create(['title' => 'Later', 'starts_on' => '2026-09-20', 'next_run_on' => '2026-09-20']);
        $sooner = RecurringRule::factory()->for($user)->create(['title' => 'Sooner', 'starts_on' => '2026-09-10', 'next_run_on' => '2026-09-10']);
        $off = RecurringRule::factory()->for($user)->create(['title' => 'Off', 'starts_on' => '2026-09-09', 'next_run_on' => '2026-09-09', 'active' => false]);
        $income = RecurringRule::factory()->for($user)->income()->create(['starts_on' => '2026-09-15', 'next_run_on' => '2026-09-15']);
        RecurringRule::factory()->create(['starts_on' => '2026-09-09', 'next_run_on' => '2026-09-09']);

        Sanctum::actingAs($user, [TokenAbility::RecurringRead->value]);

        $response = $this->getJson('/api/v1/recurring')->assertOk()->assertJsonCount(4, 'data');

        $this->assertSame(
            [$sooner->uuid, $income->uuid, $later->uuid, $off->uuid],
            array_column($response->json('data'), 'uuid'),
        );
        $this->assertArrayNotHasKey('id', $response->json('data.0'));

        $this->getJson('/api/v1/recurring?kind=income')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $income->uuid);

        $this->getJson('/api/v1/recurring?kind=weekly')->assertUnprocessable();
    }

    public function test_someone_elses_rule_is_forbidden_unless_manage_all_is_held(): void
    {
        $user = User::factory()->create();
        $theirs = RecurringRule::factory()->income()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $this->getJson("/api/v1/recurring/{$theirs->uuid}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->patchJson("/api/v1/recurring/{$theirs->uuid}", $this->incomePayload())->assertForbidden();
        $this->deleteJson("/api/v1/recurring/{$theirs->uuid}")->assertForbidden();

        // The row kind's manage_all, not the role: granting it to a plain
        // user must work, and the expense one must not open an income rule.
        $user->givePermissionTo(Permission::ExpensesManageAll->value);
        $this->getJson("/api/v1/recurring/{$theirs->uuid}")->assertForbidden();

        $user->givePermissionTo(Permission::IncomesManageAll->value);
        $this->getJson("/api/v1/recurring/{$theirs->uuid}")->assertOk();
        $this->deleteJson("/api/v1/recurring/{$theirs->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('recurring_rules', ['id' => $theirs->id]);
    }

    public function test_a_token_without_the_ability_is_refused(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $rule = RecurringRule::factory()->for($user)->create();

        // Logging rows by hand says nothing about scheduling them.
        Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value, TokenAbility::ExpensesWrite->value]);

        $this->getJson('/api/v1/recurring')
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid ability provided.');
        $this->postJson('/api/v1/recurring', $this->expensePayload($category))->assertForbidden();

        // And a read-only recurring token cannot write.
        Sanctum::actingAs($user, [TokenAbility::RecurringRead->value]);

        $this->getJson('/api/v1/recurring')->assertOk();
        $this->postJson('/api/v1/recurring', $this->expensePayload($category))
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid ability provided.');
        $this->deleteJson("/api/v1/recurring/{$rule->uuid}")->assertForbidden();
    }

    public function test_the_policy_bites_when_the_row_kinds_permission_is_revoked(): void
    {
        $user = User::factory()->create();
        $user->revokePermissionTo(Permission::IncomesCreate->value);

        Sanctum::actingAs($user, $this->readWrite());

        $this->postJson('/api/v1/recurring', $this->incomePayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this->assertSame(0, RecurringRule::count());
    }

    public function test_validation_ties_the_category_to_the_kind(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $this->postJson('/api/v1/recurring', $this->incomePayload(['category_uuid' => $category->uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_uuid');

        $this->postJson('/api/v1/recurring', $this->expensePayload($category, ['category_uuid' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_uuid');

        $this->postJson('/api/v1/recurring', $this->expensePayload($category, ['starts_on' => '2025-09-07']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_on');

        $this->postJson('/api/v1/recurring', $this->expensePayload($category, ['ends_on' => '2026-09-08']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->postJson('/api/v1/recurring', $this->expensePayload($category, ['frequency' => 'fortnightly']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('frequency');

        $this->assertSame(0, RecurringRule::count());
    }

    public function test_the_kind_cannot_change_on_update(): void
    {
        $user = User::factory()->create();
        $rule = RecurringRule::factory()->for($user)->income()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $this->patchJson("/api/v1/recurring/{$rule->uuid}", $this->incomePayload(['kind' => 'expense']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');

        // Echoing the current kind back, as a full-shape PATCH does, is fine.
        $this->patchJson("/api/v1/recurring/{$rule->uuid}", $this->incomePayload(['title' => 'Freelance']))
            ->assertOk()
            ->assertJsonPath('data.kind', 'income')
            ->assertJsonPath('data.title', 'Freelance');
    }

    public function test_update_changes_future_rows_only_and_moves_the_cursor_forward(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $uuid = $this->postJson('/api/v1/recurring', $this->expensePayload($category, [
            'frequency' => 'daily',
            'starts_on' => '2026-09-06',
        ]))->assertCreated()->json('data.uuid');

        $this->assertSame(3, Expense::count());

        // Weekly from the 1st: the first occurrence on or after today is the
        // 15th, and the days already written are left exactly as they were.
        $this->patchJson("/api/v1/recurring/{$uuid}", $this->expensePayload($category, [
            'title' => 'Rent (raised)',
            'amount' => '475',
            'frequency' => 'weekly',
            'starts_on' => '2026-09-01',
        ]))
            ->assertOk()
            ->assertJsonPath('data.amount', '475.00')
            ->assertJsonPath('data.next_run_on', '2026-09-15')
            ->assertJsonPath('data.last_run_on', '2026-09-08');

        $this->assertSame(3, Expense::count());
        $this->assertSame('450.0000', Expense::query()->orderByDesc('spent_on')->first()->price);
        $this->assertSame(0, Expense::where('item->en', 'Rent (raised)')->count());
    }

    public function test_deleting_a_rule_keeps_the_rows_it_wrote(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user, [...$this->readWrite(), TokenAbility::ExpensesRead->value]);

        $uuid = $this->postJson('/api/v1/recurring', $this->expensePayload($category))
            ->assertCreated()
            ->json('data.uuid');

        $this->deleteJson("/api/v1/recurring/{$uuid}")->assertNoContent();

        $this->assertSame(0, RecurringRule::count());
        $this->assertSame(1, Expense::count());
        $this->assertNull(Expense::sole()->recurring_rule_id);

        $this->getJson('/api/v1/expenses')->assertOk()->assertJsonPath('data.0.recurring', false);
    }

    public function test_a_riel_amount_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();

        Sanctum::actingAs($user, $this->readWrite());

        $this->postJson('/api/v1/recurring', $this->incomePayload(['amount' => '20000', 'currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '5.00');

        $this->assertSame('5.0000', RecurringRule::sole()->amount);
        $this->assertSame('5.0000', Income::sole()->amount);
    }

    public function test_an_unknown_or_non_uuid_key_is_a_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::RecurringRead->value]);

        $this->getJson('/api/v1/recurring/1')->assertNotFound();
        $this->getJson('/api/v1/recurring/0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a')->assertNotFound();
    }
}
