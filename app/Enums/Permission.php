<?php

namespace App\Enums;

/**
 * Every permission the app checks.
 *
 * One enum, so the seeder, the policies and the permissions drawer all read from
 * the same list — a string typed into a policy that no seeder creates is a check
 * that silently never passes.
 *
 * Two axes, and the difference matters:
 *
 *   <subject>.<verb>   acts on your own records. Every signed-in user needs
 *                      these to use the app at all, so they are the baseline the
 *                      'user' role is seeded with.
 *   <subject>.*_all    acts on everyone's. This is the administrative half.
 *
 * Ownership is still checked in the policies on top of the plain verbs — holding
 * expenses.update lets you edit your own expense, not anybody's.
 */
enum Permission: string
{
    // --- Pages ------------------------------------------------------------
    case DashboardView = 'dashboard.view';
    case ReportsView = 'reports.view';

    // --- Expenses ---------------------------------------------------------
    case ExpensesView = 'expenses.view';
    case ExpensesCreate = 'expenses.create';
    case ExpensesUpdate = 'expenses.update';
    case ExpensesDelete = 'expenses.delete';
    case ExpensesViewAll = 'expenses.view_all';
    case ExpensesManageAll = 'expenses.manage_all';

    // --- Categories (shared by everyone) ----------------------------------
    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';

    // --- Budgets ----------------------------------------------------------
    case BudgetsView = 'budgets.view';
    case BudgetsCreate = 'budgets.create';
    case BudgetsUpdate = 'budgets.update';
    case BudgetsDelete = 'budgets.delete';
    case BudgetsManageAll = 'budgets.manage_all';

    // --- Account ----------------------------------------------------------
    case ProfileUpdate = 'profile.update';
    case PasswordUpdate = 'password.update';

    // --- Administration ---------------------------------------------------
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case SettingsBranding = 'settings.branding';
    case SettingsFaq = 'settings.faq';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => __('View the dashboard'),
            self::ReportsView => __('View reports'),

            self::ExpensesView => __('View own expenses'),
            self::ExpensesCreate => __('Add expenses'),
            self::ExpensesUpdate => __('Edit own expenses'),
            self::ExpensesDelete => __('Delete own expenses'),
            self::ExpensesViewAll => __('View everyone’s expenses'),
            self::ExpensesManageAll => __('Edit and delete any expense'),

            self::CategoriesView => __('View categories'),
            self::CategoriesCreate => __('Add categories'),
            self::CategoriesUpdate => __('Edit categories'),
            self::CategoriesDelete => __('Delete categories'),

            self::BudgetsView => __('View own budgets'),
            self::BudgetsCreate => __('Set budgets'),
            self::BudgetsUpdate => __('Change budgets'),
            self::BudgetsDelete => __('Remove budgets'),
            self::BudgetsManageAll => __('Manage anyone’s budgets'),

            self::ProfileUpdate => __('Change own name and email'),
            self::PasswordUpdate => __('Change own password'),

            self::UsersView => __('View users'),
            self::UsersManage => __('Create, edit and suspend users'),
            self::SettingsBranding => __('Change branding and colours'),
            self::SettingsFaq => __('Manage the help / FAQ entries'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DashboardView => __('Without this, signing in lands on the expenses page.'),
            self::ReportsView => __('Trends and comparisons over time.'),

            self::ExpensesView => __('Their own log. Revoking this hides the page.'),
            self::ExpensesCreate => __('The quick-add form.'),
            self::ExpensesUpdate => __('Their own only, unless granted the one below.'),
            self::ExpensesDelete => __('Their own only, unless granted the one below.'),
            self::ExpensesViewAll => __('Adds the “Everyone” view on the expenses page.'),
            self::ExpensesManageAll => __('Not just their own.'),

            self::CategoriesView => __('Needed to file an expense against one.'),
            self::CategoriesCreate => __('Includes naming one inline from the expense dialog.'),
            self::CategoriesUpdate => __('Categories are shared, so this changes them for everyone.'),
            self::CategoriesDelete => __('Only categories with no expenses can be deleted.'),

            self::BudgetsView => __('Their own budgets page.'),
            self::BudgetsCreate => __('Set a budget where none exists yet.'),
            self::BudgetsUpdate => __('Change one already set.'),
            self::BudgetsDelete => __('Clear a budget.'),
            self::BudgetsManageAll => __('Not just their own.'),

            self::ProfileUpdate => __('Revoke this where names and emails come from elsewhere.'),
            self::PasswordUpdate => __('Revoke this where passwords are managed centrally.'),

            self::UsersView => __('See the user list in settings.'),
            self::UsersManage => __('Includes assigning roles. Granting permissions stays admin-only.'),
            self::SettingsBranding => __('The app name, logo, favicon and colours.'),
            self::SettingsFaq => __('Write and order the questions on the help page.'),
        };
    }

    /** The heading this sits under in the drawer. */
    public function group(): string
    {
        return match ($this) {
            self::DashboardView, self::ReportsView => __('Pages'),

            self::ExpensesView, self::ExpensesCreate, self::ExpensesUpdate,
            self::ExpensesDelete, self::ExpensesViewAll, self::ExpensesManageAll => __('Expenses'),

            self::CategoriesView, self::CategoriesCreate,
            self::CategoriesUpdate, self::CategoriesDelete => __('Categories'),

            self::BudgetsView, self::BudgetsCreate, self::BudgetsUpdate,
            self::BudgetsDelete, self::BudgetsManageAll => __('Budgets'),

            self::ProfileUpdate, self::PasswordUpdate => __('Account'),

            self::UsersView, self::UsersManage, self::SettingsBranding, self::SettingsFaq => __('Administration'),
        };
    }

    /**
     * Everything.
     *
     * @return array<int, string>
     */
    public static function forAdmin(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * What every signed-in person needs to use the app for themselves.
     *
     * This is not "nothing to see here" — it is load-bearing. The plain verbs are
     * required by the policies, so a user without them cannot add an expense or
     * open the dashboard. Adding a case above without adding it here (when it
     * belongs to self-service) silently breaks every new non-admin.
     *
     * @return array<int, string>
     */
    public static function forUser(): array
    {
        return [
            self::DashboardView->value,
            self::ReportsView->value,

            self::ExpensesView->value,
            self::ExpensesCreate->value,
            self::ExpensesUpdate->value,
            self::ExpensesDelete->value,

            // Read, and add one inline while logging. Editing and deleting a
            // shared category stays with the admin.
            self::CategoriesView->value,
            self::CategoriesCreate->value,

            self::BudgetsView->value,
            self::BudgetsCreate->value,
            self::BudgetsUpdate->value,
            self::BudgetsDelete->value,

            self::ProfileUpdate->value,
            self::PasswordUpdate->value,
        ];
    }

    /**
     * The starting set for a role.
     *
     * A role is a template here, not a live grant: permissions are stored on the
     * user, so they can be edited per person afterwards. See RoleSeeder for why.
     *
     * @return array<int, string>
     */
    public static function defaultsFor(RoleName $role): array
    {
        return match ($role) {
            // Identical sets. A super admin is not protected by holding more
            // permissions — there are none left to hold — but by UserPolicy
            // refusing to let anything touch the account.
            RoleName::SuperAdmin, RoleName::Admin => self::forAdmin(),
            RoleName::User => self::forUser(),
        };
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
