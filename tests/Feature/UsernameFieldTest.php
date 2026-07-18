<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The username field: optional, unique when set, and shared between the admin's
 * user form and a person's own profile form.
 *
 * The rules live in App\Rules\UsernameRules precisely so the two screens cannot
 * disagree about what a valid handle is — these tests exercise both.
 */
class UsernameFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::Admin);

        return $user;
    }

    private function updateProfile(User $user, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            ...$overrides,
        ]);
    }

    public function test_a_user_can_set_their_username(): void
    {
        $user = User::factory()->create(['username' => null]);

        $this->updateProfile($user, ['username' => 'koeuk'])->assertSessionHasNoErrors();

        $this->assertSame('koeuk', $user->fresh()->username);
    }

    public function test_it_is_optional(): void
    {
        // The column is nullable and nobody should have to invent a handle to
        // save an unrelated change.
        $user = User::factory()->create(['username' => null]);

        $this->updateProfile($user)->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->username);
    }

    public function test_clearing_it_stores_null_not_an_empty_string(): void
    {
        /*
         * The one that bites: '' would occupy the unique index as a real value,
         * so exactly one account could hold the empty username and every other
         * person clearing the field would be told it is taken.
         */
        $user = User::factory()->create(['username' => 'koeuk']);

        $this->updateProfile($user, ['username' => ''])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->username);

        // And a second account can do the same without colliding.
        $other = User::factory()->create(['username' => 'other']);
        $this->updateProfile($other, ['username' => ''])->assertSessionHasNoErrors();

        $this->assertNull($other->fresh()->username);
    }

    public function test_it_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => null]);

        $this->updateProfile($user, ['username' => 'taken'])
            ->assertSessionHasErrors('username');

        $this->assertNull($user->fresh()->username);
    }

    public function test_uniqueness_is_case_insensitive(): void
    {
        // The column inherits a _ci collation, so "Koeuk" and "koeuk" collide —
        // two accounts nobody could tell apart would otherwise be possible. The
        // lowercase rule rejects it before the index has to.
        User::factory()->create(['username' => 'koeuk']);
        $user = User::factory()->create(['username' => null]);

        $this->updateProfile($user, ['username' => 'Koeuk'])
            ->assertSessionHasErrors('username');
    }

    public function test_keeping_your_own_username_is_not_a_collision(): void
    {
        $user = User::factory()->create(['username' => 'koeuk']);

        $this->updateProfile($user, ['username' => 'koeuk', 'name' => 'New Name'])
            ->assertSessionHasNoErrors();

        $this->assertSame('koeuk', $user->fresh()->username);
        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_it_rejects_malformed_usernames(): void
    {
        $invalid = [
            'too short' => 'ab',
            'too long' => str_repeat('a', 31),
            'uppercase' => 'Koeuk',
            'spaces' => 'koeuk kos',
            // Barred so a handle can never look like an email — the login split
            // keys on '@', and an ambiguous value would be unreachable.
            'at sign' => 'koeuk@kos',
            // Barred so a handle cannot end in what looks like a format
            // extension, and cannot be mistaken for an address.
            'dot' => 'koeuk.kos',
            'leading hyphen' => '-koeuk',
            'leading underscore' => '_koeuk',
        ];

        foreach ($invalid as $label => $username) {
            $user = User::factory()->create(['username' => null]);

            $this->updateProfile($user, ['username' => $username])
                ->assertSessionHasErrors('username');

            $this->assertNull($user->fresh()->username, "[{$label}] should not be stored");
        }
    }

    public function test_valid_shapes_are_accepted(): void
    {
        foreach (['abc', 'koeuk-kos', 'koeuk_kos', 'user123', str_repeat('a', 30)] as $username) {
            $user = User::factory()->create(['username' => null]);

            $this->updateProfile($user, ['username' => $username])
                ->assertSessionHasNoErrors();

            $this->assertSame($username, $user->fresh()->username, "[{$username}] should be valid");
        }
    }

    public function test_an_admin_can_set_a_username_on_someone_elses_account(): void
    {
        // The other form that accepts one — same rules, via UsernameRules.
        $admin = $this->admin();
        $target = User::factory()->create(['username' => null]);

        $this->actingAs($admin)->patch("/settings/users/{$target->uuid}", [
            'name' => $target->name,
            'username' => 'assigned',
            'email' => $target->email,
            'role' => RoleName::User->value,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertSame('assigned', $target->fresh()->username);
    }

    public function test_the_admin_form_rejects_a_duplicate_too(): void
    {
        $admin = $this->admin();
        User::factory()->create(['username' => 'taken']);
        $target = User::factory()->create(['username' => null]);

        $this->actingAs($admin)->patch("/settings/users/{$target->uuid}", [
            'name' => $target->name,
            'username' => 'taken',
            'email' => $target->email,
            'role' => RoleName::User->value,
            'status' => 'active',
        ])->assertSessionHasErrors('username');
    }

    public function test_the_api_returns_the_username(): void
    {
        $user = User::factory()->create(['username' => 'koeuk']);

        \Laravel\Sanctum\Sanctum::actingAs($user, [TokenAbility::ExpensesRead->value]);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'koeuk');
    }

    public function test_the_api_login_accepts_a_username(): void
    {
        User::factory()->create(['username' => 'koeuk', 'email' => 'k@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'koeuk',
            'password' => 'password',
            'device_name' => 'Test',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_the_api_login_still_accepts_an_email(): void
    {
        User::factory()->create(['username' => 'koeuk', 'email' => 'k@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'k@example.com',
            'password' => 'password',
            'device_name' => 'Test',
        ])->assertOk();
    }

    public function test_the_api_login_rejects_an_unknown_username_without_leaking(): void
    {
        User::factory()->create(['username' => 'koeuk']);

        $this->postJson('/api/v1/login', [
            'email' => 'nobody',
            'password' => 'password',
            'device_name' => 'Test',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    }
}
