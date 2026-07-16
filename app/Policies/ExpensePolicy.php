<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $this->owns($user, $expense) || $user->isAdmin();
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->owns($user, $expense) || $user->isAdmin();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->owns($user, $expense) || $user->isAdmin();
    }

    private function owns(User $user, Expense $expense): bool
    {
        return $expense->user_id === $user->id;
    }
}
