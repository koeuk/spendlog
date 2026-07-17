<?php

namespace Tests\Feature\Settings;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner account: same permissions as an admin, but out of reach of the
 * user-management screen.
 *
 * Every test here goes at a route rather than calling the policy, because the
 * protection has to hold against a hand-made request — the UI hiding a button
 * proves nothing.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::SuperAdmin);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::Admin);

        return $user;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    /** @return array<string, mixed> */
    private function editPayload(User $target, array $overrides = []): array
    {
        return array_merge([
            'name' => $target->name,
            'email' => $target->email,
            'role' => RoleName::User->value,
            'status' => UserStatus::Active->value,
        ], $overrides);
    }

    // ---------------------------------------------------------------- powers

    /**
     * The whole point of folding SuperAdmin into isAdmin(): without it the
     * "super" account would be the weaker of the two.
     */
    public function test_a_super_admin_counts_as_an_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $this->assertTrue($superAdmin->isAdmin());
        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    public function test_an_admin_is_not_a_super_admin(): void
    {
        $this->assertFalse($this->admin()->isSuperAdmin());
    }

    public function test_a_super_admin_holds_every_permission(): void
    {
        $superAdmin = $this->superAdmin();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                $superAdmin->hasPermissionTo($permission->value),
                "Super admin is missing {$permission->value}.",
            );
        }
    }

    public function test_a_super_admin_can_administer_other_users(): void
    {
        $target = $this->user();

        $this->actingAs($this->superAdmin())
            ->put(route('users.permissions', $target->uuid), [
                'permissions' => [Permission::ExpensesViewAll->value],
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasDirectPermission(Permission::ExpensesViewAll->value));
    }

    // ------------------------------------------------------------ protection

    public function test_an_admin_cannot_edit_a_super_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($this->admin())
            ->patch(route('users.update', $superAdmin->uuid), $this->editPayload($superAdmin, [
                'name' => 'Renamed',
            ]))
            ->assertForbidden();

        $this->assertSame($superAdmin->name, $superAdmin->fresh()->name);
    }

    /**
     * The quiet takeover: change the owner's email to one you control, then use
     * the password-reset flow. Editing has to be barred, not just role changes.
     */
    public function test_an_admin_cannot_move_a_super_admins_email_to_their_own(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($this->admin())
            ->patch(route('users.update', $superAdmin->uuid), $this->editPayload($superAdmin, [
                'email' => 'attacker@example.com',
            ]))
            ->assertForbidden();

        $this->assertNotSame('attacker@example.com', $superAdmin->fresh()->email);
    }

    public function test_an_admin_cannot_delete_a_super_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($this->admin())
            ->delete(route('users.destroy', $superAdmin->uuid))
            ->assertForbidden();

        $this->assertModelExists($superAdmin);
    }

    public function test_an_admin_cannot_suspend_a_super_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($this->admin())
            ->patch(route('users.status', $superAdmin->uuid), ['status' => UserStatus::Suspended->value])
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $superAdmin->fresh()->status);
    }

    public function test_an_admin_cannot_demote_a_super_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($this->admin())
            ->patch(route('users.update', $superAdmin->uuid), $this->editPayload($superAdmin, [
                'role' => RoleName::User->value,
            ]))
            ->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
    }

    /** Stripping the permissions would hollow out the account without touching the role. */
    public function test_an_admin_cannot_edit_a_super_admins_permissions(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($this->admin())
            ->put(route('users.permissions', $superAdmin->uuid), ['permissions' => []])
            ->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->hasPermissionTo(Permission::UsersManage->value));
    }

    /**
     * "No one" is meant literally — a second super admin is no more able to
     * delete the first than an admin is. Anything less leaves a path from the UI
     * to removing the owner account.
     */
    public function test_not_even_another_super_admin_can_delete_one(): void
    {
        $target = $this->superAdmin();

        $this->actingAs($this->superAdmin())
            ->delete(route('users.destroy', $target->uuid))
            ->assertForbidden();

        $this->assertModelExists($target);
    }

    public function test_a_super_admin_cannot_delete_itself(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $superAdmin->uuid))
            ->assertForbidden();

        $this->assertModelExists($superAdmin);
    }

    // ------------------------------------------------------------ assignment

    /**
     * The dropdown not offering it is presentation. This is the rule: a hand
     * -made POST must not be able to mint an untouchable account.
     */
    public function test_an_admin_cannot_create_a_super_admin(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Backdoor',
                'email' => 'backdoor@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => RoleName::SuperAdmin->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'backdoor@example.com']);
    }

    public function test_an_admin_cannot_promote_an_existing_user_to_super_admin(): void
    {
        $target = $this->user();

        $this->actingAs($this->admin())
            ->patch(route('users.update', $target->uuid), $this->editPayload($target, [
                'role' => RoleName::SuperAdmin->value,
            ]))
            ->assertSessionHasErrors('role');

        $this->assertFalse($target->fresh()->isSuperAdmin());
    }

    /** Not even a super admin can hand the role out through the app. */
    public function test_a_super_admin_cannot_promote_someone_through_the_form_either(): void
    {
        $target = $this->user();

        $this->actingAs($this->superAdmin())
            ->patch(route('users.update', $target->uuid), $this->editPayload($target, [
                'role' => RoleName::SuperAdmin->value,
            ]))
            ->assertSessionHasErrors('role');

        $this->assertFalse($target->fresh()->isSuperAdmin());
    }

    public function test_the_role_dropdown_does_not_offer_super_admin(): void
    {
        $response = $this->actingAs($this->admin())->get(route('users.index'));

        $response->assertOk();

        preg_match('/data-page="([^"]*)"/', $response->getContent(), $matches);
        $props = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true)['props'];

        $this->assertSame(
            [RoleName::Admin->value, RoleName::User->value],
            array_column($props['roles'], 'value'),
        );
    }

    public function test_assignable_excludes_super_admin_but_keeps_the_rest(): void
    {
        $this->assertSame(
            [RoleName::Admin, RoleName::User],
            RoleName::assignable(),
        );
    }

    // ----------------------------------------------------------- last admin

    /**
     * A super admin administers the app, so an install holding one is not
     * stranded — the last-admin guard must not fire on the strength of the
     * super admin being invisible to it.
     */
    public function test_the_last_admin_may_be_demoted_when_a_super_admin_exists(): void
    {
        $this->superAdmin();
        $lastAdmin = $this->admin();
        $actor = $this->admin();

        $this->actingAs($actor)
            ->patch(route('users.update', $lastAdmin->uuid), $this->editPayload($lastAdmin, [
                'role' => RoleName::User->value,
            ]))
            ->assertRedirect();

        $this->assertFalse($lastAdmin->fresh()->isAdmin());
    }
}
