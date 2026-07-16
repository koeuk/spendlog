<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Idempotent on purpose: this runs on every deploy, and syncPermissions()
     * means a permission removed from the enum is also revoked from the role,
     * rather than lingering in the database forever.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $role->syncPermissions(match ($roleName) {
                RoleName::Admin => PermissionEnum::forAdmin(),
                RoleName::User => PermissionEnum::forUser(),
            });
        }

        // The registrar caches the whole permission map; without this the app
        // keeps answering from the pre-seed picture for the rest of the request.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
