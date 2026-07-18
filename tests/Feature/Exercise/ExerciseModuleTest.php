<?php

namespace Tests\Feature\Exercise;

use App\Enums\MuscleGroup;
use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The module's front door: who can reach it, and what they can do once inside.
 *
 * The gating tests here are the load-bearing ones. Exercise ships locked, and a
 * regression that quietly opens it would hand every account a tracker nobody
 * asked for — which no other test in the suite would notice.
 */
class ExerciseModuleTest extends TestCase
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

    private function plainUser(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user->fresh();
    }

    /* --- gating --------------------------------------------------------- */

    public function test_a_plain_user_is_refused_every_exercise_route(): void
    {
        $user = $this->plainUser();

        foreach ([
            route('exercise.dashboard'),
            route('exercise.workouts.index'),
            route('exercise.types.index'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_a_plain_user_cannot_write_workouts_by_posting_directly(): void
    {
        $user = $this->plainUser();

        $this->actingAs($user)
            ->post(route('exercise.workouts.store'), ['performed_on' => '2026-07-18'])
            ->assertForbidden();

        $this->assertSame(0, Workout::count());
    }

    public function test_a_granted_user_reaches_the_module(): void
    {
        $user = $this->grantedUser();

        $this->actingAs($user)->get(route('exercise.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('exercise.workouts.index'))->assertOk();
        $this->actingAs($user)->get(route('exercise.types.index'))->assertOk();
    }

    public function test_an_admin_reaches_the_module_without_being_granted_it(): void
    {
        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);

        $this->actingAs($admin->fresh())->get(route('exercise.dashboard'))->assertOk();
    }

    /**
     * The switcher renders off auth.permissions, so this is what decides whether
     * the module is visible in the header at all.
     */
    public function test_the_permission_is_absent_from_a_plain_users_shared_props(): void
    {
        $user = $this->plainUser();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $permissions = $response->viewData('page')['props']['auth']['permissions'];

        $this->assertNotContains(Permission::ExerciseView->value, $permissions);
    }

    public function test_the_permission_is_present_once_granted(): void
    {
        $user = $this->grantedUser();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $permissions = $response->viewData('page')['props']['auth']['permissions'];

        $this->assertContains(Permission::ExerciseView->value, $permissions);
    }

    /* --- logging -------------------------------------------------------- */

    public function test_a_workout_is_logged_with_its_sets(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        $this->actingAs($user)->post(route('exercise.workouts.store'), [
            'performed_on' => '2026-07-18',
            'duration_seconds' => 3600,
            'notes' => 'Felt strong',
            'weight_unit' => 'kg',
            'sets' => [
                ['exercise_type_uuid' => $bench->uuid, 'reps' => 8, 'weight' => 60],
                ['exercise_type_uuid' => $bench->uuid, 'reps' => 8, 'weight' => 62.5],
            ],
        ])->assertRedirect();

        $workout = Workout::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(3600, $workout->duration_seconds);
        $this->assertCount(2, $workout->sets);
        // Assigned from array order, never trusted from input.
        $this->assertSame([1, 2], $workout->sets->pluck('set_no')->all());
        $this->assertSame(980.0, $workout->load('sets')->volumeKg());
    }

    public function test_weights_entered_in_pounds_are_stored_as_kilograms(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        $this->actingAs($user)->post(route('exercise.workouts.store'), [
            'performed_on' => '2026-07-18',
            'weight_unit' => 'lb',
            'sets' => [['exercise_type_uuid' => $bench->uuid, 'reps' => 5, 'weight' => 225]],
        ])->assertRedirect();

        $set = Workout::where('user_id', $user->id)->firstOrFail()->sets->first();

        $this->assertEqualsWithDelta(102.058, (float) $set->weight_kg, 0.001);
    }

    public function test_a_session_with_no_sets_is_allowed(): void
    {
        $user = $this->grantedUser();

        $this->actingAs($user)->post(route('exercise.workouts.store'), [
            'performed_on' => '2026-07-18',
            'duration_seconds' => 1800,
        ])->assertRedirect();

        $workout = Workout::where('user_id', $user->id)->firstOrFail();

        $this->assertCount(0, $workout->sets);
        $this->assertSame(1800, $workout->duration_seconds);
    }

    public function test_a_future_workout_is_rejected(): void
    {
        $user = $this->grantedUser();

        $this->actingAs($user)
            ->post(route('exercise.workouts.store'), ['performed_on' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('performed_on');

        $this->assertSame(0, Workout::count());
    }

    /**
     * A guessed UUID must not file a set against somebody else's private
     * movement — existence alone is not the check.
     */
    public function test_a_set_cannot_reference_another_users_private_exercise(): void
    {
        $user = $this->grantedUser();
        $other = User::factory()->create();
        $theirs = ExerciseType::factory()->for($other)->create(['name' => 'Their Secret Lift']);

        $this->actingAs($user)->post(route('exercise.workouts.store'), [
            'performed_on' => '2026-07-18',
            'sets' => [['exercise_type_uuid' => $theirs->uuid, 'reps' => 5, 'weight' => 50]],
        ])->assertSessionHasErrors('sets.0.exercise_type_uuid');

        $this->assertSame(0, Workout::count());
    }

    public function test_a_user_cannot_see_or_touch_another_users_workout(): void
    {
        $user = $this->grantedUser();
        $other = $this->grantedUser();
        $theirs = Workout::factory()->for($other)->create();

        $this->actingAs($user)
            ->put(route('exercise.workouts.update', $theirs->uuid), ['performed_on' => '2026-07-18'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('exercise.workouts.destroy', $theirs->uuid))
            ->assertForbidden();

        $response = $this->actingAs($user)->get(route('exercise.workouts.index'));
        $this->assertCount(0, $response->viewData('page')['props']['workouts']);
    }

    public function test_updating_a_workout_replaces_its_sets(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();
        $squat = ExerciseType::whereJsonContains('name->en', 'Squat')->firstOrFail();

        $workout = $user->workouts()->create(['performed_on' => '2026-07-18']);
        $workout->sets()->create(['exercise_type_id' => $bench->id, 'set_no' => 1, 'reps' => 8, 'weight_kg' => 60]);

        $this->actingAs($user)->put(route('exercise.workouts.update', $workout->uuid), [
            'performed_on' => '2026-07-18',
            'weight_unit' => 'kg',
            'sets' => [['exercise_type_uuid' => $squat->uuid, 'reps' => 5, 'weight' => 100]],
        ])->assertRedirect();

        $sets = $workout->fresh()->sets;

        $this->assertCount(1, $sets);
        $this->assertSame($squat->id, $sets->first()->exercise_type_id);
    }

    /* --- the catalogue -------------------------------------------------- */

    public function test_a_created_movement_belongs_to_its_author_not_the_globals(): void
    {
        $user = $this->grantedUser();

        $this->actingAs($user)->post(route('exercise.types.store'), [
            'name' => ['en' => 'Zercher Squat'],
            'muscle_group' => MuscleGroup::Legs->value,
        ])->assertRedirect();

        $type = ExerciseType::whereJsonContains('name->en', 'Zercher Squat')->firstOrFail();

        $this->assertSame($user->id, $type->user_id);
        $this->assertFalse($type->isGlobal());
        // Colour follows the muscle group when none was picked.
        $this->assertSame(MuscleGroup::Legs->color(), $type->color);
    }

    public function test_a_plain_granted_user_cannot_edit_a_global_movement(): void
    {
        $user = $this->grantedUser();
        $global = ExerciseType::whereJsonContains('name->en', 'Squat')->firstOrFail();

        $this->actingAs($user)->put(route('exercise.types.update', $global->uuid), [
            'name' => ['en' => 'Hijacked'],
            'muscle_group' => MuscleGroup::Legs->value,
        ])->assertForbidden();

        $this->assertSame('Squat', $global->fresh()->getTranslation('name', 'en'));
    }

    public function test_an_admin_can_edit_a_global_movement(): void
    {
        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);
        $global = ExerciseType::whereJsonContains('name->en', 'Squat')->firstOrFail();

        $this->actingAs($admin->fresh())->put(route('exercise.types.update', $global->uuid), [
            'name' => ['en' => 'Back Squat'],
            'muscle_group' => MuscleGroup::Legs->value,
        ])->assertRedirect();

        $this->assertSame('Back Squat', $global->fresh()->getTranslation('name', 'en'));
    }

    public function test_two_people_may_both_invent_a_movement_with_the_same_name(): void
    {
        $first = $this->grantedUser();
        $second = $this->grantedUser();

        foreach ([$first, $second] as $user) {
            $this->actingAs($user)->post(route('exercise.types.store'), [
                'name' => ['en' => 'Sled Push'],
                'muscle_group' => MuscleGroup::Legs->value,
            ])->assertRedirect();
        }

        $this->assertSame(2, ExerciseType::whereJsonContains('name->en', 'Sled Push')->count());
    }

    public function test_a_user_cannot_name_a_movement_they_already_have(): void
    {
        $user = $this->grantedUser();

        $this->actingAs($user)->post(route('exercise.types.store'), [
            'name' => ['en' => 'Sled Push'],
            'muscle_group' => MuscleGroup::Legs->value,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('exercise.types.store'), [
            'name' => ['en' => 'sled push'],
            'muscle_group' => MuscleGroup::Legs->value,
        ])->assertSessionHasErrors('name.en');

        $this->assertSame(1, ExerciseType::whereJsonContains('name->en', 'Sled Push')->count());
    }

    /**
     * The FK is restrictOnDelete, so a performed movement is history. The
     * controller turns the integrity error into a message rather than a 500.
     */
    public function test_a_movement_that_has_been_performed_cannot_be_deleted(): void
    {
        $user = $this->grantedUser();
        $type = ExerciseType::factory()->for($user)->create(['name' => 'Doomed Lift']);

        $workout = $user->workouts()->create(['performed_on' => '2026-07-18']);
        $workout->sets()->create(['exercise_type_id' => $type->id, 'set_no' => 1, 'reps' => 5, 'weight_kg' => 40]);

        $this->actingAs($user)
            ->delete(route('exercise.types.destroy', $type->uuid))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('exercise_types', ['id' => $type->id]);
    }

    /* --- settings ------------------------------------------------------- */

    public function test_the_unit_preference_is_reachable_by_a_granted_non_admin(): void
    {
        $user = $this->grantedUser();

        $this->actingAs($user)->get(route('exercise-settings.edit'))->assertOk();

        $this->actingAs($user)
            ->post(route('exercise-settings.update'), ['default_weight_unit' => 'lb'])
            ->assertRedirect();

        $this->assertDatabaseHas('app_settings', ['default_weight_unit' => 'lb']);
    }

    public function test_a_plain_user_cannot_reach_the_unit_preference(): void
    {
        $this->actingAs($this->plainUser())
            ->get(route('exercise-settings.edit'))
            ->assertForbidden();
    }

    /**
     * An API client that omits weight_unit should behave like the UI, which
     * starts on the configured default rather than a hardcoded kilogram.
     */
    public function test_an_omitted_unit_falls_back_to_the_configured_default(): void
    {
        $user = $this->grantedUser();
        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        $this->actingAs($user)->post(route('exercise-settings.update'), ['default_weight_unit' => 'lb']);

        $this->actingAs($user)->post(route('exercise.workouts.store'), [
            'performed_on' => '2026-07-18',
            'sets' => [['exercise_type_uuid' => $bench->uuid, 'reps' => 5, 'weight' => 225]],
        ])->assertRedirect();

        $set = Workout::where('user_id', $user->id)->firstOrFail()->sets->first();

        // 225 read as pounds, not as kilograms.
        $this->assertEqualsWithDelta(102.058, (float) $set->weight_kg, 0.001);
    }
}
