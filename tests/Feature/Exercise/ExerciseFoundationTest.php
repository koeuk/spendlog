<?php

namespace Tests\Feature\Exercise;

use App\Enums\MuscleGroup;
use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\WeightUnit;
use App\Models\ExerciseType;
use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The exercise module's foundations: the catalogue, the ownership rules and the
 * unit conversion everything above them assumes.
 */
class ExerciseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_catalogue_seeds_globally_and_is_idempotent(): void
    {
        $this->seed(ExerciseTypeSeeder::class);
        $count = ExerciseType::whereNull('user_id')->count();

        $this->seed(ExerciseTypeSeeder::class);

        $this->assertSame($count, ExerciseType::whereNull('user_id')->count());
        $this->assertGreaterThan(20, $count);
        $this->assertSame(0, ExerciseType::whereNotNull('user_id')->count());
    }

    public function test_seeded_types_carry_their_muscle_groups_colour(): void
    {
        $this->seed(ExerciseTypeSeeder::class);

        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();

        $this->assertSame(MuscleGroup::Chest, $bench->muscle_group);
        $this->assertSame(MuscleGroup::Chest->color(), $bench->color);
        $this->assertFalse($bench->is_cardio);

        $running = ExerciseType::whereJsonContains('name->en', 'Running')->firstOrFail();

        $this->assertSame(MuscleGroup::Cardio, $running->muscle_group);
        $this->assertTrue($running->is_cardio);
    }

    public function test_re_seeding_does_not_touch_a_users_own_type_of_the_same_name(): void
    {
        $this->seed(ExerciseTypeSeeder::class);
        $user = User::factory()->create();

        $mine = $user->exerciseTypes()->create([
            'name' => 'Running',
            'muscle_group' => MuscleGroup::Legs,
            'is_cardio' => false,
        ]);

        $this->seed(ExerciseTypeSeeder::class);

        // The global "Running" is cardio; this one must survive as filed.
        $this->assertSame(MuscleGroup::Legs, $mine->fresh()->muscle_group);
        $this->assertFalse($mine->fresh()->is_cardio);
        $this->assertSame($user->id, $mine->fresh()->user_id);
    }

    public function test_available_to_returns_globals_plus_own_but_never_anothers(): void
    {
        $this->seed(ExerciseTypeSeeder::class);
        $user = User::factory()->create();
        $other = User::factory()->create();

        ExerciseType::factory()->for($user)->create(['name' => 'My Lift']);
        ExerciseType::factory()->for($other)->create(['name' => 'Their Lift']);

        $names = ExerciseType::availableTo($user->id)->get()
            ->map(fn (ExerciseType $t) => $t->getTranslation('name', 'en'))
            ->all();

        $this->assertContains('My Lift', $names);
        $this->assertContains('Bench Press', $names);
        $this->assertNotContains('Their Lift', $names);
    }

    /**
     * The OR inside availableTo() is parenthesised. Without the grouping it
     * would escape any WHERE chained onto it and quietly widen the result.
     */
    public function test_available_to_does_not_widen_an_existing_filter(): void
    {
        $this->seed(ExerciseTypeSeeder::class);
        $user = User::factory()->create();

        $chest = ExerciseType::query()
            ->where('muscle_group', MuscleGroup::Chest->value)
            ->availableTo($user->id)
            ->get();

        $this->assertGreaterThan(0, $chest->count());
        $this->assertLessThan(ExerciseType::count(), $chest->count());
        $chest->each(fn (ExerciseType $t) => $this->assertSame(MuscleGroup::Chest, $t->muscle_group));
    }

    public function test_volume_sums_strength_sets_and_ignores_cardio(): void
    {
        $this->seed(ExerciseTypeSeeder::class);
        $user = User::factory()->create();

        $bench = ExerciseType::whereJsonContains('name->en', 'Bench Press')->firstOrFail();
        $running = ExerciseType::whereJsonContains('name->en', 'Running')->firstOrFail();

        $workout = $user->workouts()->create([
            'performed_on' => '2026-07-18',
            'duration_seconds' => 3600,
        ]);

        $workout->sets()->create(['exercise_type_id' => $bench->id, 'set_no' => 1, 'reps' => 8, 'weight_kg' => 60]);
        $workout->sets()->create(['exercise_type_id' => $bench->id, 'set_no' => 2, 'reps' => 8, 'weight_kg' => 62.5]);
        // Cardio contributes no load, and must not blow up the sum with nulls.
        $workout->sets()->create(['exercise_type_id' => $running->id, 'set_no' => 3, 'distance_m' => 5000, 'duration_seconds' => 1680]);

        $this->assertSame(980.0, $workout->load('sets')->volumeKg());
        $this->assertCount(3, $workout->sets);
    }

    public function test_user_id_cannot_be_mass_assigned_onto_a_workout(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $workout = $user->workouts()->create([
            'performed_on' => '2026-07-18',
            'user_id' => $victim->id,
        ]);

        $this->assertSame($user->id, $workout->fresh()->user_id);
    }

    public function test_in_month_scope_bounds_the_month(): void
    {
        $user = User::factory()->create();

        Workout::factory()->for($user)->on('2026-07-01')->create();
        Workout::factory()->for($user)->on('2026-07-31')->create();
        Workout::factory()->for($user)->on('2026-06-30')->create();
        Workout::factory()->for($user)->on('2026-08-01')->create();

        $this->assertSame(2, Workout::forUser($user->id)->inMonth('2026-07')->count());
    }

    /**
     * Storing one canonical unit is lossy at the last place — 225 lb comes back
     * as 224.999 — exactly as storing every amount in USD is. What matters is
     * that the error stays far below anything a person could load on a bar.
     */
    public function test_pounds_round_trip_through_stored_kilograms(): void
    {
        $kg = WeightUnit::Lb->toKg(225);

        $this->assertSame('102.058', $kg);
        $this->assertEqualsWithDelta(225.0, (float) WeightUnit::Lb->fromKg($kg), 0.01);

        // Kilograms are the stored unit, so they are exact by construction.
        $this->assertSame('60.000', WeightUnit::Kg->toKg(60));
        $this->assertSame('60.000', WeightUnit::Kg->fromKg('60.000'));
    }

    /**
     * The module ships locked. If this fails, every account in the app just
     * gained the exercise tracker.
     */
    public function test_exercise_permissions_are_not_in_the_user_baseline(): void
    {
        $baseline = Permission::forUser();

        $this->assertNotContains(Permission::ExerciseView->value, $baseline);
        $this->assertNotContains(Permission::ExerciseCreate->value, $baseline);
        $this->assertNotContains(Permission::ExerciseUpdate->value, $baseline);
        $this->assertNotContains(Permission::ExerciseDelete->value, $baseline);
        $this->assertNotContains(Permission::ExerciseTypesManage->value, $baseline);
    }

    public function test_a_new_user_cannot_see_exercise_but_an_admin_can(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);

        $this->assertFalse($user->hasPermissionTo(Permission::ExerciseView->value));
        $this->assertTrue($admin->hasPermissionTo(Permission::ExerciseView->value));
    }

    public function test_an_admin_can_grant_exercise_access_to_a_specific_user(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        $user->givePermissionTo(Permission::ExerciseView->value);

        $this->assertTrue($user->fresh()->hasPermissionTo(Permission::ExerciseView->value));
        // Granting the module must not disturb the rest of their access.
        $this->assertTrue($user->fresh()->hasPermissionTo(Permission::ExpensesView->value));
    }
}
