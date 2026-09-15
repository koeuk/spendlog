<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Borrowing;
use App\Models\User;

/**
 * Two gates on every action, as IncomePolicy has: the permission says whether
 * they may do this kind of thing at all, ownership says whether they may do it
 * to *this* row. Holding borrowings.update edits your own; editing someone
 * else's needs manage_all.
 *
 * Repayments have no policy of their own: adding or removing one changes what
 * the borrowing still owes, so it is an update *of the borrowing* and is
 * authorised as one.
 */
class BorrowingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::BorrowingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::BorrowingsCreate->value);
    }

    public function view(User $user, Borrowing $borrowing): bool
    {
        if ($this->owns($user, $borrowing)) {
            return $user->hasPermissionTo(Permission::BorrowingsView->value);
        }

        return $user->hasPermissionTo(Permission::BorrowingsManageAll->value);
    }

    public function update(User $user, Borrowing $borrowing): bool
    {
        if ($this->owns($user, $borrowing)) {
            return $user->hasPermissionTo(Permission::BorrowingsUpdate->value);
        }

        return $user->hasPermissionTo(Permission::BorrowingsManageAll->value);
    }

    public function delete(User $user, Borrowing $borrowing): bool
    {
        if ($this->owns($user, $borrowing)) {
            return $user->hasPermissionTo(Permission::BorrowingsDelete->value);
        }

        return $user->hasPermissionTo(Permission::BorrowingsManageAll->value);
    }

    private function owns(User $user, Borrowing $borrowing): bool
    {
        return $borrowing->user_id === $user->id;
    }
}
