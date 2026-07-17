<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Seeding is the only way a super admin can come into existence, so it is worth
 * as much care as the policy that protects one.
 */
class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): ?User
    {
        return User::where('email', config('spendlog.super_admin.email'))->first();
    }

    public function test_seeding_creates_the_owner_account_with_every_permission(): void
    {
        $this->seed(UserSeeder::class);

        $user = $this->superAdmin();

        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
        $this->assertCount(count(Permission::cases()), $user->permissions);
        $this->assertTrue(Hash::check(config('spendlog.super_admin.password'), $user->password));
    }

    /**
     * UserSeeder puts accounts on roles, so the roles have to exist first. Run
     * on its own it once created the owner and *then* threw "no role named
     * super_admin", leaving a row with no role and no permissions — an account
     * that looked seeded and could do nothing.
     *
     * The global TestCase seeder hides this, so it is undone here first. That is
     * the only way this test means anything.
     */
    public function test_seeding_users_alone_still_produces_a_working_owner(): void
    {
        $this->artisan('migrate:fresh');

        $this->seed(UserSeeder::class);

        $user = $this->superAdmin();

        $this->assertNotNull($user, 'The owner account was not created at all.');
        $this->assertTrue($user->isSuperAdmin(), 'The owner was created without its role.');
        $this->assertNotCount(0, $user->permissions, 'The owner was created with no permissions.');
    }

    /**
     * The seeder runs on every deploy. If it rewrote the password each time, any
     * change the owner made through their profile would be silently undone.
     */
    public function test_re_seeding_does_not_reset_a_password_the_owner_changed(): void
    {
        $this->seed(UserSeeder::class);

        $user = $this->superAdmin();
        $user->password = 'set-by-the-owner';
        $user->save();

        $this->seed(UserSeeder::class);

        $user = $this->superAdmin();

        $this->assertTrue(Hash::check('set-by-the-owner', $user->password));
        $this->assertFalse(Hash::check(config('spendlog.super_admin.password'), $user->password));
    }

    /** Re-seeding must still repair the role, which is not the owner's to change. */
    public function test_re_seeding_restores_the_role_if_it_was_lost(): void
    {
        $this->seed(UserSeeder::class);

        $user = $this->superAdmin();
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $this->seed(UserSeeder::class);

        $user = $this->superAdmin();

        $this->assertTrue($user->isSuperAdmin());
        $this->assertCount(count(Permission::cases()), $user->permissions);
    }

    public function test_re_seeding_does_not_create_a_second_owner(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(
            1,
            User::where('email', config('spendlog.super_admin.email'))->count(),
        );
    }
}
