<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertSuperAdmin();

        $this->upsertUser('admin@spendlog.test', 'Admin', RoleName::Admin);
        $this->upsertUser('user@spendlog.test', 'Test User', RoleName::User);
    }

    /**
     * The owner account.
     *
     * Seeded rather than offered in the role dropdown, because a super admin is
     * out of reach of the user-management screen: if the UI could mint one, an
     * admin could create an account that no admin could ever undo.
     *
     * Its password is set once, when the row is created, and never touched
     * again — unlike the throwaway accounts below. This seeder runs on every
     * deploy, and resetting the owner's password on each one would undo any
     * change made through the profile page, quietly and repeatedly.
     */
    private function upsertSuperAdmin(): void
    {
        $email = config('spendlog.super_admin.email');

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = config('spendlog.super_admin.name');
            $user->password = Hash::make(config('spendlog.super_admin.password'));
            $user->email_verified_at = now();
            $user->save();
        }

        // Re-applied every run, so the account cannot end up holding the role
        // without the permissions it is supposed to carry.
        $user->applyRole(RoleName::SuperAdmin);
    }

    /** Development logins. Reset to a known password on every run, by design. */
    private function upsertUser(string $email, string $name, RoleName $role): void
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->name = $name;
        $user->password = Hash::make('password');
        $user->email_verified_at = now();

        $user->save();

        $user->applyRole($role);
    }
}
