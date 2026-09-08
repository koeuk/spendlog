<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
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
}
