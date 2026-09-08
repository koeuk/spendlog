<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RecurringKind;
use App\Models\RecurringRule;
use App\Models\User;

/**
 * A rule is guarded like the rows it writes: an expense rule by the expense
 * permissions, an income rule by the income ones. No permissions of its own,
 * so a person who may log an expense may also schedule one, and nobody may
 * schedule what they could not log by hand.
 *
 * Two gates on every row action, as ExpensePolicy has: the kind's verb on
 * your own rule, the kind's manage_all on someone else's.
 */
class RecurringRulePolicy
{
    /** Either kind's view: the list is filtered to the kinds actually held. */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ExpensesView->value)
            || $user->hasPermissionTo(Permission::IncomesView->value);
    }

    public function create(User $user, RecurringKind $kind): bool
    {
        return $user->hasPermissionTo($kind->create()->value);
    }

    public function view(User $user, RecurringRule $rule): bool
    {
        if ($this->owns($user, $rule)) {
            return $user->hasPermissionTo($rule->kind->view()->value);
        }

        return $user->hasPermissionTo($rule->kind->manageAll()->value);
    }

    public function update(User $user, RecurringRule $rule): bool
    {
        if ($this->owns($user, $rule)) {
            return $user->hasPermissionTo($rule->kind->update()->value);
        }

        return $user->hasPermissionTo($rule->kind->manageAll()->value);
    }

    public function delete(User $user, RecurringRule $rule): bool
    {
        if ($this->owns($user, $rule)) {
            return $user->hasPermissionTo($rule->kind->delete()->value);
        }

        return $user->hasPermissionTo($rule->kind->manageAll()->value);
    }

    private function owns(User $user, RecurringRule $rule): bool
    {
        return $rule->user_id === $user->id;
    }
}
