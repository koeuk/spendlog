<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The seeder runs on every deploy, so what it does to existing accounts matters
 * as much as what it creates.
 */
class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_grant_nothing_on_their_own(): void
    {
        $this->seed(RoleSeeder::class);

        // The whole permission model rests on this. If a role ever carried
        // permissions, unticking a box in the drawer would silently do nothing.
        foreach (RoleName::cases() as $role) {
            $this->assertCount(0, Role::findByName($role->value, 'web')->permissions);
        }
    }

    /**
     * Accounts the old registration flow created have no role and no
     * permissions. They cannot open a single page, and nothing else in the app
     * would ever give them one, so the seeder has to adopt them.
     */
    public function test_an_account_left_without_a_role_is_rescued_as_an_ordinary_user(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $this->seed(RoleSeeder::class);

        $user = $user->fresh();

        $this->assertTrue($user->hasRole(RoleName::User->value));
        $this->assertEqualsCanonicalizing(
            Permission::forUser(),
            $user->permissions->pluck('name')->all(),
        );
    }

    /**
     * The backfill only ever adds. It runs on every deploy, so a sync would wipe
     * whatever an admin had granted one person through the drawer.
     */
    public function test_the_backfill_leaves_hand_granted_permissions_alone(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::ExpensesViewAll->value);

        $this->seed(RoleSeeder::class);

        $this->assertTrue($user->fresh()->hasPermissionTo(Permission::ExpensesViewAll->value));
    }

    /** An admin must not be quietly demoted to the user defaults. */
    public function test_the_backfill_keeps_an_admin_on_the_admin_defaults(): void
    {
        $user = User::factory()->admin()->create();

        $this->seed(RoleSeeder::class);

        $this->assertTrue($user->fresh()->hasPermissionTo(Permission::UsersManage->value));
    }
}
