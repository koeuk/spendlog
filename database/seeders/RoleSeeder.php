<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are templates, not live grants.
 *
 * A role deliberately holds no permissions. Everything is stored on the user, and
 * a role only decides the starting set — applied when the account is created or
 * its role changes.
 *
 * Why: spatie has no "deny". If the role granted expenses.create, then
 * hasPermissionTo() returns true no matter what the user's own permissions say —
 * so unticking it for one person would be impossible, and a checkbox that cannot
 * take anything away is a lie. Storing per user makes every box mean what it
 * appears to mean.
 *
 * The cost, stated plainly: adding a case to the enum no longer reaches existing
 * accounts. Re-run this seeder to top them up — it only ever adds.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            // Explicitly emptied: a role that still granted permissions would
            // override anything unticked on the user.
            $role->syncPermissions([]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->backfillUsers();
    }

    /**
     * Give every existing account its role's defaults, on top of whatever it
     * already has.
     *
     * Additive on purpose. A sync would wipe permissions granted to one person
     * through the drawer, and this runs on every deploy.
     */
    private function backfillUsers(): void
    {
        DB::transaction(function () {
            User::with('roles', 'permissions')->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $roleName = $user->roles->first()?->name;
                    $role = $roleName ? RoleName::tryFrom($roleName) : null;

                    if (! $role) {
                        // No role at all. These are the accounts self-registration
                        // created before it applied one — skipping them would leave
                        // them stranded with no permissions and no way to earn any.
                        // Treated as ordinary users, which is what they signed up as.
                        $role = RoleName::User;
                        $user->assignRole($role->value);
                    }

                    $defaults = PermissionEnum::defaultsFor($role);
                    $current = $user->permissions->pluck('name')->all();

                    $user->syncPermissions(array_values(array_unique([...$current, ...$defaults])));
                }
            });
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
