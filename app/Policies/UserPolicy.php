<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Managing accounts is admin-only, with two extra guards that matter more than
 * the role check:
 *
 *   - an admin cannot delete, suspend or demote themselves, so the person
 *     clicking cannot lock themselves out mid-action;
 *   - the last remaining admin cannot be removed by anyone, so the install
 *     cannot end up with nobody able to administer it.
 *
 * Both are enforced here rather than in the UI, because hiding a button is not a
 * permission — the request still exists.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Deleting takes the account and, by cascade, every expense on it. Blocked
     * for yourself and for the last admin.
     */
    public function delete(User $user, User $target): bool
    {
        if (! $user->isAdmin() || $user->is($target)) {
            return false;
        }

        return ! $this->isLastAdmin($target);
    }

    /** Suspending yourself would end your own session on the next request. */
    public function suspend(User $user, User $target): bool
    {
        if (! $user->isAdmin() || $user->is($target)) {
            return false;
        }

        return ! $this->isLastAdmin($target);
    }

    /**
     * Changing a role can also remove the last admin, so it carries the same
     * guard as deleting — just reached a different way.
     */
    public function changeRole(User $user, User $target): bool
    {
        if (! $user->isAdmin() || $user->is($target)) {
            return false;
        }

        return ! $this->isLastAdmin($target);
    }

    /**
     * Only counts admins who could actually sign in — a suspended admin is not a
     * way back into the install.
     */
    private function isLastAdmin(User $target): bool
    {
        if (! $target->isAdmin()) {
            return false;
        }

        $others = User::query()
            ->whereKeyNot($target->getKey())
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::Admin->value))
            ->count();

        return $others === 0;
    }
}
