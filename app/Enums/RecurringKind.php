<?php

namespace App\Enums;

/**
 * Which table a recurring rule materialises into.
 *
 * A rule carries no permissions of its own: it is an expense or an income
 * that repeats, so the row kind's permissions rule it. The helpers below are
 * what RecurringRulePolicy and the token abilities read, so "an expense rule
 * is guarded like an expense" is written down once.
 */
enum RecurringKind: string
{
    case Expense = 'expense';
    case Income = 'income';

    /**
     * The token ability that scopes writing this kind of row. A rule writes
     * rows of its kind, so scheduling one needs the same client scope as
     * writing one by hand.
     */
    public function writeAbility(): TokenAbility
    {
        return match ($this) {
            self::Expense => TokenAbility::ExpensesWrite,
            self::Income => TokenAbility::IncomesWrite,
        };
    }

    public function view(): Permission
    {
        return match ($this) {
            self::Expense => Permission::ExpensesView,
            self::Income => Permission::IncomesView,
        };
    }

    public function create(): Permission
    {
        return match ($this) {
            self::Expense => Permission::ExpensesCreate,
            self::Income => Permission::IncomesCreate,
        };
    }

    public function update(): Permission
    {
        return match ($this) {
            self::Expense => Permission::ExpensesUpdate,
            self::Income => Permission::IncomesUpdate,
        };
    }

    public function delete(): Permission
    {
        return match ($this) {
            self::Expense => Permission::ExpensesDelete,
            self::Income => Permission::IncomesDelete,
        };
    }

    public function manageAll(): Permission
    {
        return match ($this) {
            self::Expense => Permission::ExpensesManageAll,
            self::Income => Permission::IncomesManageAll,
        };
    }
}
