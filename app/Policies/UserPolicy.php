<?php

namespace App\Policies;

use App\Enums\Permission;
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
        return $user->hasPermissionTo(Permission::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::UsersManage->value);
    }

    public function update(User $user, User $target): bool
    {
        if ($this->isOutOfReach($target)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::UsersManage->value);
    }

    /**
     * Granting permissions is the one act that can hand out more of itself, so
     * it asks for the Admin role rather than a permission. Gated on
     * `users.manage` it would propagate: a non-admin granted that permission
     * cannot edit their own, but could grant it to a second account, which
     * could grant it back — two ordinary users reaching full rights with no
     * admin involved.
     */
    public function managePermissions(User $user, User $target): bool
    {
        if ($this->isOutOfReach($target)) {
            return false;
        }

        // Editing your own permissions is how an admin accidentally locks
        // themselves out of the very screen they are standing on.
        return $user->isAdmin() && ! $user->is($target);
    }

    /**
     * Deleting takes the account and, by cascade, every expense on it. Blocked
     * for yourself and for the last admin.
     */
    public function delete(User $user, User $target): bool
    {
        if ($this->isOutOfReach($target)) {
            return false;
        }

        if (! $user->hasPermissionTo(Permission::UsersManage->value) || $user->is($target)) {
            return false;
        }

        return ! $this->isLastAdmin($target);
    }

    /** Suspending yourself would end your own session on the next request. */
    public function suspend(User $user, User $target): bool
    {
        if ($this->isOutOfReach($target)) {
            return false;
        }

        if (! $user->hasPermissionTo(Permission::UsersManage->value) || $user->is($target)) {
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
        if ($this->isOutOfReach($target)) {
            return false;
        }

        if (! $user->hasPermissionTo(Permission::UsersManage->value) || $user->is($target)) {
            return false;
        }

        return ! $this->isLastAdmin($target);
    }

    /**
     * A super admin cannot be acted on from the user-management screen — by
     * anyone, including another super admin and including itself.
     *
     * Not "admins may not touch it, super admins may": that rule sounds stronger
     * and is weaker, because it still leaves a path from the UI to demoting or
     * deleting the owner account. The account maintains itself through its own
     * profile page, which does not come through this policy.
     *
     * This is the first check in every ability below rather than the last, so a
     * new permission can never quietly reopen the door.
     */
    private function isOutOfReach(User $target): bool
    {
        return $target->isSuperAdmin();
    }

    /**
     * Only counts administrators who could actually sign in — a suspended one is
     * not a way back into the install.
     *
     * Super admins count. They administer the app like any admin, so an install
     * holding one is not locked out, and refusing to count them would block the
     * last *admin* from being demoted when a perfectly good owner account exists.
     */
    private function isLastAdmin(User $target): bool
    {
        if (! $target->isAdmin()) {
            return false;
        }

        $others = User::query()
            ->whereKeyNot($target->getKey())
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                RoleName::Admin->value,
                RoleName::SuperAdmin->value,
            ]))
            ->count();

        return $others === 0;
    }
}
