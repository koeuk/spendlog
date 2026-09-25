<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\SavingsEntry;
use App\Models\SavingsPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The activity log: written from model events for whoever is signed in, read
 * back through one endpoint that shows you yours and admins everyone's.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(RoleName $role = RoleName::User): User
    {
        $user = User::factory()->create();
        $user->applyRole($role);

        return $user;
    }

    // ----------------------------------------------------------- writing

    public function test_creating_a_record_is_logged_to_the_actor(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Income::factory()->for($user)->create(['source' => 'Salary', 'amount' => 1200]);

        $log = ActivityLog::sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(ActivityLog::CREATED, $log->action);
        $this->assertSame(Income::class, $log->subject_type);
        $this->assertSame('Salary · $1,200.00', $log->subject_label);
        $this->assertNull($log->changes);
    }

    public function test_updating_a_record_logs_what_changed_with_names_not_ids(): void
    {
        $user = $this->user();
        $food = Category::factory()->create(['name' => 'Food']);
        $drink = Category::factory()->create(['name' => 'Drink']);
        $expense = Expense::factory()->for($user)->for($food)->create(['item' => 'Lunch', 'price' => 3, 'spent_on' => '2026-09-01']);

        $this->actingAs($user);

        $expense->update(['price' => 4.5, 'category_id' => $drink->id, 'spent_on' => '2026-09-02']);

        $log = ActivityLog::where('action', ActivityLog::UPDATED)->sole();

        $this->assertSame('Lunch · $4.50', $log->subject_label);
        $this->assertEquals(['from' => 'Food', 'to' => 'Drink'], $log->changes['category_id']);
        $this->assertEquals(['from' => '2026-09-01', 'to' => '2026-09-02'], $log->changes['spent_on']);
        $this->assertSame('4.5000', $log->changes['price']['to']);
    }

    public function test_a_save_that_changes_nothing_tracked_is_not_logged(): void
    {
        $user = $this->user();
        $income = Income::factory()->for($user)->create();

        $this->actingAs($user);

        $income->touch();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_deleting_a_record_keeps_its_label(): void
    {
        $user = $this->user();
        $income = Income::factory()->for($user)->create(['source' => 'Bonus', 'amount' => 50]);

        $this->actingAs($user);

        $income->delete();

        $log = ActivityLog::sole();

        $this->assertSame(ActivityLog::DELETED, $log->action);
        $this->assertSame('Bonus · $50.00', $log->subject_label);
    }

    public function test_nothing_is_logged_without_a_signed_in_actor(): void
    {
        Income::factory()->create();

        $this->assertSame(0, ActivityLog::count());
    }

    // ----------------------------------------------------------- reading

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/activity')->assertUnauthorized();
    }

    public function test_index_lists_only_the_callers_activity_newest_first(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user);
        Income::factory()->for($user)->create(['source' => 'First']);
        $this->travel(1)->minutes();
        Income::factory()->for($user)->create(['source' => 'Second']);

        $this->actingAs($other);
        Income::factory()->for($other)->create(['source' => 'Theirs']);

        // A deliberately narrow token: reading your own log needs no ability.
        Sanctum::actingAs($user, [TokenAbility::DashboardRead->value]);

        $response = $this->getJson('/api/v1/activity')->assertOk()->assertJsonCount(2, 'data');

        $this->assertStringStartsWith('Second', $response->json('data.0.label'));
        $this->assertStringStartsWith('First', $response->json('data.1.label'));
        $this->assertSame('income', $response->json('data.0.subject'));
        $this->assertSame('created', $response->json('data.0.action'));
        $this->assertSame($user->name, $response->json('data.0.user.name'));
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
    }

    public function test_an_admin_can_list_everyones_activity(): void
    {
        $admin = $this->user(RoleName::Admin);
        $user = $this->user();

        $this->actingAs($user);
        Income::factory()->for($user)->create(['source' => 'Theirs']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/activity?scope=all')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($user->name, $response->json('data.0.user.name'));
    }

    public function test_a_regular_user_cannot_list_everyones_activity(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/activity?scope=all')->assertForbidden();
    }

    public function test_subject_narrows_the_log_to_the_kinds_asked_for(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Income::factory()->for($user)->create(['source' => 'Salary']);
        SavingsPlan::factory()->for($user)->forMonth('2026-09')->create(['amount' => 100]);
        SavingsEntry::factory()->for($user)->create(['amount' => 40, 'saved_on' => '2026-09-02']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/activity?subject=savings_plan,savings_entry')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            ['savings_plan', 'savings_entry'],
            array_column($response->json('data'), 'subject'),
        );

        $this->getJson('/api/v1/activity?subject=savings_plan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'savings_plan');
    }

    public function test_an_unknown_subject_is_a_422_rather_than_an_empty_list(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/activity?subject=plans')
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject');
    }

    public function test_subject_cannot_reach_a_model_the_log_does_not_expose(): void
    {
        $user = $this->user();

        Sanctum::actingAs($user);

        // A class name, not a kind: the filter speaks the API's vocabulary
        // only, so nothing outside ActivityLog::SUBJECTS is addressable.
        $this->getJson('/api/v1/activity?subject='.urlencode(User::class))
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject');
    }

    // --------------------------------------------------- person and date

    public function test_an_admin_can_narrow_everyones_activity_to_one_person(): void
    {
        $admin = $this->user(RoleName::Admin);
        $one = $this->user();
        $two = $this->user();

        $this->actingAs($one);
        Income::factory()->for($one)->create(['source' => 'One']);
        $this->actingAs($two);
        Income::factory()->for($two)->create(['source' => 'Two']);

        Sanctum::actingAs($admin);

        // Naming a person is enough; it implies scope=all.
        $response = $this->getJson('/api/v1/activity?user='.$two->uuid)->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($two->name, $response->json('data.0.user.name'));
    }

    public function test_a_regular_user_may_name_only_themselves(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user);
        Income::factory()->for($user)->create(['source' => 'Mine']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/activity?user='.$user->uuid)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/activity?user='.$other->uuid)->assertForbidden();
    }

    public function test_an_unknown_person_is_a_422(): void
    {
        Sanctum::actingAs($this->user(RoleName::Admin));

        $this->getJson('/api/v1/activity?user=not-a-uuid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    public function test_a_date_window_covers_whole_days_and_a_date_time_is_exact(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->travelTo(now()->setDate(2026, 9, 1)->setTime(9, 0));
        Income::factory()->for($user)->create(['source' => 'First']);
        $this->travelTo(now()->setDate(2026, 9, 10)->setTime(23, 30));
        Income::factory()->for($user)->create(['source' => 'Tenth']);
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(8, 0));
        Income::factory()->for($user)->create(['source' => 'Twentieth']);

        Sanctum::actingAs($user);

        // "To the 10th" includes 23:30 on the 10th.
        $response = $this->getJson('/api/v1/activity?from=2026-09-02&to=2026-09-10')->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringStartsWith('Tenth', $response->json('data.0.label'));

        // A time narrows inside the day.
        $this->getJson('/api/v1/activity?from=2026-09-10T12:00&to=2026-09-10T20:00')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/activity?from=2026-09-10')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_end_before_the_start_is_a_422(): void
    {
        Sanctum::actingAs($this->user());

        $this->getJson('/api/v1/activity?from=2026-09-10&to=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
