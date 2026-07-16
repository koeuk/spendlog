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
        $this->upsertUser('admin@spendlog.test', 'Admin', RoleName::Admin);
        $this->upsertUser('user@spendlog.test', 'Test User', RoleName::User);
    }

    private function upsertUser(string $email, string $name, RoleName $role): void
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->name = $name;
        $user->password = Hash::make('password');
        $user->email_verified_at = now();

        $user->save();

        $user->syncRoles([$role->value]);
    }
}
