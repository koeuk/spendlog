<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SavingsEntry;
use App\Models\User;

/**
 * The ledger's own policy.
 *
 * Entries used to be authorised through their goal — a deposit changed the
 * goal's balance, so it was an update *of the goal*. With goals gone an entry
 * stands on its own, and gets the same owner-vs-manage_all pair every other
 * row in the app has.
 */
class SavingsEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::SavingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::SavingsCreate->value);
    }

    public function view(User $user, SavingsEntry $entry): bool
    {
        if ($this->owns($user, $entry)) {
            return $user->hasPermissionTo(Permission::SavingsView->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    public function update(User $user, SavingsEntry $entry): bool
    {
        if ($this->owns($user, $entry)) {
            return $user->hasPermissionTo(Permission::SavingsUpdate->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    public function delete(User $user, SavingsEntry $entry): bool
    {
        if ($this->owns($user, $entry)) {
            return $user->hasPermissionTo(Permission::SavingsDelete->value);
        }

        return $user->hasPermissionTo(Permission::SavingsManageAll->value);
    }

    private function owns(User $user, SavingsEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }
}
