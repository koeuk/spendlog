<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\IncomeSource;
use App\Models\User;

/**
 * The income permissions, not a set of its own: a source is the name on an
 * income, and anyone who may keep their income may keep the names they file it
 * under. Ownership still decides whose catalogue may be touched.
 */
class IncomeSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::IncomesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::IncomesCreate->value);
    }

    public function update(User $user, IncomeSource $source): bool
    {
        if ($this->owns($user, $source)) {
            return $user->hasPermissionTo(Permission::IncomesUpdate->value);
        }

        return $user->hasPermissionTo(Permission::IncomesManageAll->value);
    }

    public function delete(User $user, IncomeSource $source): bool
    {
        if ($this->owns($user, $source)) {
            return $user->hasPermissionTo(Permission::IncomesDelete->value);
        }

        return $user->hasPermissionTo(Permission::IncomesManageAll->value);
    }

    private function owns(User $user, IncomeSource $source): bool
    {
        return $source->user_id === $user->id;
    }
}
