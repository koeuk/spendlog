<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SavingsPlan;
use App\Models\User;

/**
 * Two gates on every action, as ExpensePolicy has: the permission says whether
 * they may do this kind of thing at all, ownership says whether they may do it
 * to *this* row. Holding savings.update edits your own; editing someone else's
 * needs manage_all.
 */
class SavingsPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::SavingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::SavingsCreate->value);
    }

    public function view(User $user, SavingsPlan $plan): bool
    {
        if ($this->owns($user, $plan)) {
            return $user->hasPermissionTo(Permission::SavingsView->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    public function update(User $user, SavingsPlan $plan): bool
    {
        if ($this->owns($user, $plan)) {
            return $user->hasPermissionTo(Permission::SavingsUpdate->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    public function delete(User $user, SavingsPlan $plan): bool
    {
        if ($this->owns($user, $plan)) {
            return $user->hasPermissionTo(Permission::SavingsDelete->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    private function owns(User $user, SavingsPlan $plan): bool
    {
        return $plan->user_id === $user->id;
    }
}
