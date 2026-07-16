<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Everyone needs to read categories to log an expense against one.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Anyone may add one.
     *
     * Deliberately looser than update/delete: a category is created mid-flow from
     * the expense dialog, and making people stop to ask an admin is how expenses
     * end up filed under "Other" forever. Editing and deleting stay admin-only —
     * those change or remove a row everyone else is already using, while creating
     * only ever adds to the list.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }
}
