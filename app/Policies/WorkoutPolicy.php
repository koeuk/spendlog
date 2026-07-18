<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workout;

/**
 * Two gates on every action, as ExpensePolicy has: the permission says whether
 * they may do this kind of thing at all, ownership says whether they may do it
 * to *this* row.
 *
 * Unlike expenses there is no manage_all counterpart, and that is deliberate. A
 * training log is personal in a way a spending log filed against a shared
 * category is not — there is no admin view of everyone's workouts to support,
 * so a non-owner is refused outright rather than falling through to a wider
 * permission. If that is ever wanted, add exercise.view_all here rather than
 * loosening owns().
 */
class WorkoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExerciseView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExerciseCreate->value);
    }

    public function view(User $user, Workout $workout): bool
    {
        return $this->owns($user, $workout)
            && $user->hasPermissionTo(Permission::ExerciseView->value);
    }

    public function update(User $user, Workout $workout): bool
    {
        return $this->owns($user, $workout)
            && $user->hasPermissionTo(Permission::ExerciseUpdate->value);
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $this->owns($user, $workout)
            && $user->hasPermissionTo(Permission::ExerciseDelete->value);
    }

    private function owns(User $user, Workout $workout): bool
    {
        return $workout->user_id === $user->id;
    }
}
