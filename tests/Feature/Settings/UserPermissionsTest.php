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
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::User->value);

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

    public function test_permissions_already_granted_by_the_role_are_not_stored_as_direct(): void
    {
        $target = $this->user();
        $target->assignRole(RoleName::Admin->value);

        $this->actingAs($this->admin())
            ->put($this->url($target), ['permissions' => [Permission::UsersManage->value]])
            ->assertRedirect();

        // Kept via the role, but not duplicated as a direct grant — otherwise it
        // would outlive the role it came from.
        $target = $target->fresh();
        $this->assertTrue($target->hasPermissionTo(Permission::UsersManage->value));
        $this->assertFalse($target->hasDirectPermission(Permission::UsersManage->value));
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
