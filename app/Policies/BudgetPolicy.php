<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::BudgetsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::BudgetsCreate->value);
    }

    public function view(User $user, Budget $budget): bool
    {
        if ($this->owns($user, $budget)) {
            return $user->hasPermissionTo(Permission::BudgetsView->value);
        }

        return $user->hasPermissionTo(Permission::BudgetsManageAll->value);
    }

    public function update(User $user, Budget $budget): bool
    {
        if ($this->owns($user, $budget)) {
            return $user->hasPermissionTo(Permission::BudgetsUpdate->value);
        }

        return $user->hasPermissionTo(Permission::BudgetsManageAll->value);
    }

    public function delete(User $user, Budget $budget): bool
    {
        if ($this->owns($user, $budget)) {
            return $user->hasPermissionTo(Permission::BudgetsDelete->value);
        }

        return $user->hasPermissionTo(Permission::BudgetsManageAll->value);
    }

    private function owns(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
