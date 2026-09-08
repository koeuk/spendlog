<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SavingsGoal;
use App\Models\User;

/**
 * Two gates on every action, as ExpensePolicy has: the permission says whether
 * they may do this kind of thing at all, ownership says whether they may do it
 * to *this* row. Holding savings.update edits your own; editing someone else's
 * needs manage_all.
 *
 * Entries have no policy of their own. A deposit or withdrawal changes the
 * goal's balance, so it is authorised as an update *of the goal* — the
 * controller asks `update` on the goal before touching its ledger.
 */
class SavingsGoalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::SavingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::SavingsCreate->value);
    }

    public function view(User $user, SavingsGoal $goal): bool
    {
        if ($this->owns($user, $goal)) {
            return $user->hasPermissionTo(Permission::SavingsView->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    public function update(User $user, SavingsGoal $goal): bool
    {
        if ($this->owns($user, $goal)) {
            return $user->hasPermissionTo(Permission::SavingsUpdate->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    public function delete(User $user, SavingsGoal $goal): bool
    {
        if ($this->owns($user, $goal)) {
            return $user->hasPermissionTo(Permission::SavingsDelete->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    private function owns(User $user, SavingsGoal $goal): bool
    {
        return $goal->user_id === $user->id;
    }
}
