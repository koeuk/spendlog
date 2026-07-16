<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

/**
 * Categories are shared by everyone, which is why the verbs split unevenly:
 * creating only ever adds to the list, while editing and deleting change a row
 * other people are already filing expenses against.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CategoriesView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CategoriesCreate->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo(Permission::CategoriesUpdate->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo(Permission::CategoriesDelete->value);
    }
}
