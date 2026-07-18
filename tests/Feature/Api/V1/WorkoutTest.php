<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciseTypeSeeder::class);
    }

    private function grantedUser(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);
        $user->givePermissionTo([
            Permission::ExerciseView->value,
            Permission::ExerciseCreate->value,
            Permission::ExerciseUpdate->value,
            Permission::ExerciseDelete->value,
        ]);

        return $user->fresh();
    }

    /**
     * The abilities are derived from permissions, so an account that never got
     * the module cannot hold a token that reaches it. This is what makes the
     * API side of the lock automatic rather than a second thing to remember.
     */
    public function test_a_plain_user_is_never_granted_the_exercise_abilities(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        $grantable = TokenAbility::grantableTo($user->fresh());

        $this->assertNotContains(TokenAbility::ExerciseRead->value, $grantable);
        $this->assertNotContains(TokenAbility::ExerciseWrite->value, $grantable);
    }

    public function test_a_granted_user_is_offered_the_exercise_abilities(): void
    {
        $grantable = TokenAbility::grantableTo($this->grantedUser());

        $this->assertContains(TokenAbility::ExerciseRead->value, $grantable);
        $this->assertContains(TokenAbility::ExerciseWrite->value, $grantable);
    }

    /**
     * Even a forged token is not enough — the policy checks the user behind it.
     */
    public function test_a_token_with_the_ability_still_fails_without_the_permission(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        Sanctum::actingAs($user->fresh(), [TokenAbility::ExerciseRead->value]);

        $this->getJson('/api/v1/workouts')->assertForbidden();
    }

    public function test_a_read_token_cannot_write(): void
    {
        Sanctum::actingAs($this->grantedUser(), [TokenAbility::ExerciseRead->value]);

        $this->postJson('/api/v1/workouts', ['performed_on' => '2026-07-18'])
            ->assertForbidden();
    }

    public function test_index_returns_only_the_callers_workouts(): void
    {
        $user = $this->grantedUser();
        Workout::factory()->for($user)->create();
        Workout::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExerciseRead->value]);

        $this->getJson('/api/v1/workouts')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_workout_is_created_with_its_sets(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        Sanctum::actingAs($user, [TokenAbility::ExerciseWrite->value]);

        $response = $this->postJson('/api/v1/workouts', [
            'performed_on' => '2026-07-18',
            'duration_seconds' => 2700,
            'weight_unit' => 'lb',
            'sets' => [
                ['exercise_type_uuid' => $bench->uuid, 'reps' => 5, 'weight' => 225],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.duration_seconds', 2700);
        $response->assertJsonCount(1, 'data.sets');

        // Entered in pounds, stored and returned in kilograms.
        $this->assertEqualsWithDelta(
            102.058,
            $response->json('data.sets.0.weight_kg'),
            0.001,
        );
    }

    public function test_a_workout_never_leaks_the_internal_id(): void
    {
        $user = $this->grantedUser();
        $workout = Workout::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::ExerciseRead->value]);

        $response = $this->getJson("/api/v1/workouts/{$workout->uuid}")->assertOk();

        $this->assertArrayNotHasKey('id', $response->json('data'));
        $this->assertArrayNotHasKey('user_id', $response->json('data'));
    }

    public function test_another_users_workout_is_not_reachable(): void
    {
        $user = $this->grantedUser();
        $theirs = Workout::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::ExerciseRead->value]);

        $this->getJson("/api/v1/workouts/{$theirs->uuid}")->assertForbidden();
    }

    public function test_the_exercise_catalogue_lists_globals_plus_own(): void
    {
        $user = $this->grantedUser();
        ExerciseType::factory()->for($user)->create(['name' => 'My Lift']);
        ExerciseType::factory()->create(['name' => 'Their Lift']);

        Sanctum::actingAs($user, [TokenAbility::ExerciseRead->value]);

        $names = collect($this->getJson('/api/v1/exercises')->assertOk()->json('data'))
            ->pluck('name');

        $this->assertTrue($names->contains('My Lift'));
        $this->assertTrue($names->contains('Bench Press'));
        $this->assertFalse($names->contains('Their Lift'));
    }

    public function test_the_summary_reports_the_months_figures(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        $workout = $user->workouts()->create(['performed_on' => '2026-07-18', 'duration_seconds' => 3600]);
        $workout->sets()->create(['exercise_type_id' => $bench->id, 'set_no' => 1, 'reps' => 10, 'weight_kg' => 50]);

        Sanctum::actingAs($user, [TokenAbility::ExerciseRead->value]);

        $this->getJson('/api/v1/workouts/summary?month=2026-07')
            ->assertOk()
            ->assertJsonPath('data.month', '2026-07')
            ->assertJsonPath('data.sessions', 1)
            ->assertJsonPath('data.volume_kg', 500)
            ->assertJsonPath('data.duration_seconds', 3600);
    }

    /** 'summary' must not bind as a workout uuid — route order matters. */
    public function test_the_summary_route_is_not_shadowed_by_the_show_route(): void
    {
        Sanctum::actingAs($this->grantedUser(), [TokenAbility::ExerciseRead->value]);

        $this->getJson('/api/v1/workouts/summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['month', 'sessions', 'streak']]);
    }

    public function test_a_workout_is_deleted_with_its_sets(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        $workout = $user->workouts()->create(['performed_on' => '2026-07-18']);
        $workout->sets()->create(['exercise_type_id' => $bench->id, 'set_no' => 1, 'reps' => 5, 'weight_kg' => 40]);

        Sanctum::actingAs($user, [TokenAbility::ExerciseWrite->value]);

        $this->deleteJson("/api/v1/workouts/{$workout->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
        $this->assertDatabaseMissing('workout_sets', ['workout_id' => $workout->id]);
    }
}
