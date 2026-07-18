<?php

namespace App\Enums;

use App\Models\User;

/**
 * Sanctum token abilities — a client scope that sits *in front of* the policies,
 * never instead of them. A token limits what a client may attempt; the policy
 * still decides what the user is allowed to do. Both must pass.
 *
 * The scope is bounded by the person's permissions and derived from them (see
 * grantableTo), so the API can only ever be narrower than the web, never a
 * different answer to the same question. This is what lets a mobile app hold a
 * token that cannot touch categories even when the person holding it may.
 */
enum TokenAbility: string
{
    case ExpensesRead = 'expenses:read';
    case ExpensesWrite = 'expenses:write';
    case CategoriesRead = 'categories:read';
    case CategoriesWrite = 'categories:write';
    case BudgetsRead = 'budgets:read';
    case BudgetsWrite = 'budgets:write';
    case DashboardRead = 'dashboard:read';
    /*
     * The exercise module. Nothing extra is needed to keep these locked: the
     * abilities are derived from permissions by grantableTo below, and an
     * account without exercise.view simply never has them to give.
     */
    case ExerciseRead = 'exercise:read';
    case ExerciseWrite = 'exercise:write';

    /**
     * The permissions that justify this ability.
     *
     * A write ability spans create/update/delete because it is a scope, not a
     * decision: holding any one of them is reason enough for a client to be
     * allowed to *attempt* a write, and the policy still rules on the specific
     * action against the specific row.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ExpensesRead => [Permission::ExpensesView],
            self::ExpensesWrite => [
                Permission::ExpensesCreate,
                Permission::ExpensesUpdate,
                Permission::ExpensesDelete,
            ],
            self::CategoriesRead => [Permission::CategoriesView],
            self::CategoriesWrite => [
                Permission::CategoriesCreate,
                Permission::CategoriesUpdate,
                Permission::CategoriesDelete,
            ],
            self::BudgetsRead => [Permission::BudgetsView],
            self::BudgetsWrite => [
                Permission::BudgetsCreate,
                Permission::BudgetsUpdate,
                Permission::BudgetsDelete,
            ],
            self::DashboardRead => [Permission::DashboardView],
            self::ExerciseRead => [Permission::ExerciseView],
            self::ExerciseWrite => [
                Permission::ExerciseCreate,
                Permission::ExerciseUpdate,
                Permission::ExerciseDelete,
            ],
        };
    }

    /**
     * What a freshly issued token gets: everything this person's permissions
     * justify, and nothing they do not.
     *
     * Abilities are a client scope layered on top of the permission system, not
     * a second one beside it. They used to be a hardcoded list plus an isAdmin()
     * check, written before permissions existed — which meant the web and the
     * API disagreed about the same user, and the API always won by 403ing first.
     * Deriving both sides from permissions is what keeps them from drifting
     * apart again.
     *
     * @return array<int, string>
     */
    public static function defaults(User $user): array
    {
        return self::grantableTo($user);
    }

    /**
     * The abilities a client may legitimately ask for at login.
     *
     * A token can never carry an ability its owner's permissions do not already
     * cover, so this is the ceiling — a client is free to request less.
     *
     * @return array<int, string>
     */
    public static function grantableTo(User $user): array
    {
        return collect(self::cases())
            ->filter(fn (self $ability) => collect($ability->permissions())
                ->contains(fn (Permission $permission) => $user->hasPermissionTo($permission->value)))
            ->map(fn (self $ability) => $ability->value)
            ->values()
            ->all();
    }
}
