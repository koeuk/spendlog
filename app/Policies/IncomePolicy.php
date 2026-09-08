<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Income;
use App\Models\User;

/**
 * Two gates on every action, as ExpensePolicy has: the permission says whether
 * they may do this kind of thing at all, ownership says whether they may do it
 * to *this* row. Holding incomes.update edits your own; editing someone else's
 * needs manage_all.
 */
class IncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::IncomesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::IncomesCreate->value);
    }

    public function view(User $user, Income $income): bool
    {
        if ($this->owns($user, $income)) {
            return $user->hasPermissionTo(Permission::IncomesView->value);
        }

        return $user->hasPermissionTo(Permission::IncomesManageAll->value);
    }

    public function update(User $user, Income $income): bool
    {
        if ($this->owns($user, $income)) {
            return $user->hasPermissionTo(Permission::IncomesUpdate->value);
        }

        return $user->hasPermissionTo(Permission::IncomesManageAll->value);
    }

    public function delete(User $user, Income $income): bool
    {
        if ($this->owns($user, $income)) {
            return $user->hasPermissionTo(Permission::IncomesDelete->value);
        }

        return $user->hasPermissionTo(Permission::IncomesManageAll->value);
    }

    private function owns(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }
}
