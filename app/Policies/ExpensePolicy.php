<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

/**
 * Two gates on every action: the permission says whether they may do this kind of
 * thing at all, ownership says whether they may do it to *this* row. Holding
 * expenses.update edits your own; editing someone else's needs manage_all.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExpensesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExpensesCreate->value);
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($this->owns($user, $expense)) {
            return $user->hasPermissionTo(Permission::ExpensesView->value);
        }

        return $user->hasPermissionTo(Permission::ExpensesViewAll->value);
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($this->owns($user, $expense)) {
            return $user->hasPermissionTo(Permission::ExpensesUpdate->value);
        }

        return $user->hasPermissionTo(Permission::ExpensesManageAll->value);
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($this->owns($user, $expense)) {
            return $user->hasPermissionTo(Permission::ExpensesDelete->value);
        }

        return $user->hasPermissionTo(Permission::ExpensesManageAll->value);
    }

    private function owns(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id;
    }
}
