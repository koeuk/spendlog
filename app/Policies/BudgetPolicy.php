<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function view(User $user, Budget $budget): bool
    {
        return $this->owns($user, $budget) || $user->isAdmin();
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->owns($user, $budget) || $user->isAdmin();
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->owns($user, $budget) || $user->isAdmin();
    }

    private function owns(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
