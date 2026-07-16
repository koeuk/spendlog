<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function view(User $user, Budget $budget): bool
    {
        return $this->owns($user, $budget) || $user->hasPermissionTo(Permission::BudgetsManageAll->value);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->owns($user, $budget) || $user->hasPermissionTo(Permission::BudgetsManageAll->value);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->owns($user, $budget) || $user->hasPermissionTo(Permission::BudgetsManageAll->value);
    }

    private function owns(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
