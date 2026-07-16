<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $this->owns($user, $expense) || $user->hasPermissionTo(Permission::ExpensesViewAll->value);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->owns($user, $expense) || $user->hasPermissionTo(Permission::ExpensesManageAll->value);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->owns($user, $expense) || $user->hasPermissionTo(Permission::ExpensesManageAll->value);
    }

    private function owns(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id;
    }
}
