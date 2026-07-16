<?php

namespace App\Enums;

use App\Models\User;

/**
 * Sanctum token abilities — a second gate that sits *in front of* the policies,
 * never instead of them. A token limits what a client may attempt; the policy
 * still decides what the user is allowed to do. Both must pass.
 *
 * This is what lets a mobile app hold a token that cannot touch categories even
 * when the person holding it is an admin.
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

    /**
     * What a freshly issued token gets. Deliberately excludes categories:write:
     * category management is an admin desk job, and a lost phone should not be
     * able to rewrite the taxonomy every user's expenses hang off. An admin who
     * genuinely needs it can mint a token that asks for it explicitly.
     *
     * @return array<int, string>
     */
    public static function defaults(User $user): array
    {
        return collect(self::cases())
            ->reject(fn (self $ability) => $ability === self::CategoriesWrite)
            ->map(fn (self $ability) => $ability->value)
            ->all();
    }

    /**
     * The abilities a client may legitimately ask for at login, which for an
     * admin is everything and for everyone else is the default set.
     *
     * @return array<int, string>
     */
    public static function grantableTo(User $user): array
    {
        if ($user->isAdmin()) {
            return array_column(self::cases(), 'value');
        }

        return self::defaults($user);
    }
}
