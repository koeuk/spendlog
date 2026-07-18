<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExerciseType;
use App\Models\User;

/**
 * The catalogue has two tiers, and the split is the whole point of this policy.
 *
 * A global movement (null user_id) is shared by everyone, so editing one is an
 * administrative act — exercise.types_manage. A movement someone invented is
 * theirs, so it follows the ordinary create/update/delete verbs and ownership.
 *
 * Reading is not split: everyone who can open the module sees both tiers, which
 * is what makes the picker useful on day one.
 */
class ExerciseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExerciseView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExerciseCreate->value);
    }

    public function view(User $user, ExerciseType $type): bool
    {
        if (! $user->hasPermissionTo(Permission::ExerciseView->value)) {
            return false;
        }

        // A global is visible to all; someone else's own is visible to nobody.
        return $type->isGlobal() || $this->owns($user, $type);
    }

    public function update(User $user, ExerciseType $type): bool
    {
        if ($type->isGlobal()) {
            return $user->hasPermissionTo(Permission::ExerciseTypesManage->value);
        }

        return $this->owns($user, $type)
            && $user->hasPermissionTo(Permission::ExerciseUpdate->value);
    }

    public function delete(User $user, ExerciseType $type): bool
    {
        if ($type->isGlobal()) {
            return $user->hasPermissionTo(Permission::ExerciseTypesManage->value);
        }

        return $this->owns($user, $type)
            && $user->hasPermissionTo(Permission::ExerciseDelete->value);
    }

    private function owns(User $user, ExerciseType $type): bool
    {
        return $type->user_id !== null && $type->user_id === $user->id;
    }
}
