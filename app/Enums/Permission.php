<?php

namespace App\Enums;

/**
 * Every permission the app checks.
 *
 * One enum, so the seeder, the policies and the permissions drawer all read from
 * the same list — a string typed into a policy that no seeder creates is a check
 * that silently never passes.
 *
 * Named <subject>.<verb> to match how they group in the UI.
 */
enum Permission: string
{
    // Categories are shared, so editing one changes it for everyone.
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';

    // Seeing and touching other people's records.
    case ExpensesViewAll = 'expenses.view_all';
    case ExpensesManageAll = 'expenses.manage_all';
    case BudgetsManageAll = 'budgets.manage_all';

    // Account administration.
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    // Branding and colours.
    case SettingsBranding = 'settings.branding';

    public function label(): string
    {
        return match ($this) {
            self::CategoriesUpdate => __('Edit categories'),
            self::CategoriesDelete => __('Delete categories'),
            self::ExpensesViewAll => __('View everyone’s expenses'),
            self::ExpensesManageAll => __('Edit and delete any expense'),
            self::BudgetsManageAll => __('Manage anyone’s budgets'),
            self::UsersView => __('View users'),
            self::UsersManage => __('Create, edit and suspend users'),
            self::SettingsBranding => __('Change branding and colours'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CategoriesUpdate => __('Categories are shared, so this changes them for everyone.'),
            self::CategoriesDelete => __('Only categories with no expenses can be deleted.'),
            self::ExpensesViewAll => __('Adds the “Everyone” view on the expenses page.'),
            self::ExpensesManageAll => __('Not just their own.'),
            self::BudgetsManageAll => __('Not just their own.'),
            self::UsersView => __('See the user list in settings.'),
            self::UsersManage => __('Includes assigning roles and permissions.'),
            self::SettingsBranding => __('The app name, logo, favicon and colours.'),
        };
    }

    /** The heading this sits under in the drawer. */
    public function group(): string
    {
        return match ($this) {
            self::CategoriesUpdate, self::CategoriesDelete => __('Categories'),
            self::ExpensesViewAll, self::ExpensesManageAll => __('Expenses'),
            self::BudgetsManageAll => __('Budgets'),
            self::UsersView, self::UsersManage => __('Users'),
            self::SettingsBranding => __('Settings'),
        };
    }

    /**
     * What the admin role gets: everything. A new permission is therefore
     * granted to admins by adding the case, with no seeder edit to forget.
     *
     * @return array<int, string>
     */
    public static function forAdmin(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * What a plain user gets: nothing extra. Their own expenses and budgets are
     * covered by ownership in the policies, not by a permission.
     *
     * @return array<int, string>
     */
    public static function forUser(): array
    {
        return [];
    }

    /**
     * @return array<string, array<int, array{value: string, label: string, description: string}>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $permission) {
            $grouped[$permission->group()][] = [
                'value' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
            ];
        }

        return $grouped;
    }
}
