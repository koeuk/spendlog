<?php

namespace Tests\Feature\Settings;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Policies call hasPermissionTo(), which throws PermissionDoesNotExist
        // rather than returning false when the catalogue was never seeded.
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);

        return $admin;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    private function url(User $target): string
    {
        return route('users.permissions', $target->uuid);
    }

    public function test_admin_can_grant_permissions_to_another_user(): void
    {
        $target = $this->user();

        $this->actingAs($this->admin())
            ->put($this->url($target), ['permissions' => [Permission::ExpensesViewAll->value]])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasDirectPermission(Permission::ExpensesViewAll->value));
    }

    public function test_admin_cannot_edit_their_own_permissions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put($this->url($admin), ['permissions' => []])
            ->assertForbidden();
    }

    /**
     * The reason this gate asks for the Admin role and not `users.manage`:
     * otherwise the permission hands out more of itself. Bob cannot edit his
     * own permissions, but nothing would stop him granting Carol everything —
     * including `users.manage`, which she could hand straight back.
     */
    public function test_non_admin_holding_users_manage_cannot_edit_permissions(): void
    {
        $bob = $this->user();
        $bob->givePermissionTo(Permission::UsersManage->value);

        $carol = $this->user();

        $this->actingAs($bob)
            ->put($this->url($carol), ['permissions' => [Permission::UsersManage->value]])
            ->assertForbidden();

        $this->assertFalse($carol->fresh()->hasDirectPermission(Permission::UsersManage->value));
    }

    public function test_plain_user_cannot_edit_permissions(): void
    {
        $this->actingAs($this->user())
            ->put($this->url($this->user()), ['permissions' => []])
            ->assertForbidden();
    }

    /**
     * The ticked list is the whole truth, even for an admin.
     *
     * Nothing is filtered out against the role, because the role grants nothing:
     * a permission the drawer does not send is a permission the person loses,
     * regardless of the badge on their row.
     */
    public function test_the_submitted_list_replaces_everything_the_role_started_them_with(): void
    {
        $target = $this->user();
        $target->applyRole(RoleName::Admin);

        $this->actingAs($this->admin())
            ->put($this->url($target), ['permissions' => [Permission::UsersManage->value]])
            ->assertRedirect();

        $target = $target->fresh();
        $this->assertTrue($target->hasPermissionTo(Permission::UsersManage->value));
        $this->assertTrue($target->hasDirectPermission(Permission::UsersManage->value));

        // Still an admin by role, but the admin defaults it was created with are
        // gone — only what was ticked survives.
        $this->assertTrue($target->isAdmin());
        $this->assertFalse($target->hasPermissionTo(Permission::ExpensesViewAll->value));
    }

    public function test_unchecked_permissions_are_revoked(): void
    {
        $target = $this->user();
        $target->givePermissionTo(Permission::ExpensesViewAll->value);

        $this->actingAs($this->admin())
            ->put($this->url($target), ['permissions' => []])
            ->assertRedirect();

        $this->assertFalse($target->fresh()->hasDirectPermission(Permission::ExpensesViewAll->value));
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $target = $this->user();

        $this->actingAs($this->admin())
            ->put($this->url($target), ['permissions' => ['expenses.invent_money']])
            ->assertSessionHasErrors('permissions.0');
    }
}
