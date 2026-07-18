<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        // The limiter is a cache counter that survives between tests in the
        // same process, so an earlier test's failed logins would throttle a
        // later one.
        RateLimiter::clear('api-login');
    }

    /**
     * Suspension has to hold on the API, not just the web login. Revoking a
     * suspended user's tokens is pointless if they can immediately trade the
     * same password for a new one — the token carries abilities, not status,
     * so nothing downstream would catch it.
     */
    #[DataProvider('blockedStatuses')]
    public function test_a_blocked_account_cannot_get_a_token(UserStatus $status): void
    {
        User::factory()->create([
            'email' => 'sam@example.com',
            'status' => $status,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public static function blockedStatuses(): array
    {
        return [
            'suspended' => [UserStatus::Suspended],
            'invited' => [UserStatus::Invited],
            'archived' => [UserStatus::Archived],
        ];
    }

    /** The wording is the status's own — "suspended" is wrong for an archived account. */
    public function test_the_refusal_uses_the_wording_for_that_status(): void
    {
        User::factory()->create([
            'email' => 'sam@example.com',
            'status' => UserStatus::Archived,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ])->assertJsonPath(
            'errors.email.0',
            UserStatus::Archived->signInError(),
        );
    }

    /**
     * The login check only covers a client that comes back for a new token. A
     * phone holding one already never revisits it, and status is not carried on
     * the token — so without a checkpoint on every API request, suspension did
     * not reach an existing session at all.
     */
    public function test_an_existing_token_stops_working_once_the_account_is_suspended(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone 15', [TokenAbility::ExpensesRead->value])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/expenses')->assertOk();

        $user->update(['status' => UserStatus::Suspended]);

        // The guard caches the user it resolved for the previous request, and
        // the test process keeps one container across both. Production gets a
        // fresh one per request; without this the second call would re-check the
        // stale Active instance and the assertion would prove nothing.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/expenses')->assertForbidden();
    }

    /** The refused request also burns the token, so it cannot be retried. */
    public function test_the_suspended_account_token_is_revoked_on_the_way_out(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone 15', [TokenAbility::ExpensesRead->value])->plainTextToken;

        $user->update(['status' => UserStatus::Suspended]);

        $this->withToken($token)->getJson('/api/v1/expenses')->assertForbidden();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_returns_a_token_and_the_user(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['uuid', 'name', 'email', 'is_admin']])
            ->assertJsonPath('user.email', 'sam@example.com');

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'iPhone 15',
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_login_never_leaks_the_internal_id(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
        ]);

        $response->assertOk()->assertJsonMissingPath('user.id');
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'not-the-password',
            'device_name' => 'phone',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /**
     * A wrong password and an unknown email must be indistinguishable, or the
     * endpoint becomes a user-enumeration oracle.
     */
    public function test_unknown_email_and_wrong_password_give_the_same_error(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        $wrongPassword = $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'nope',
            'device_name' => 'phone',
        ]);

        $unknownEmail = $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'nope',
            'device_name' => 'phone',
        ]);

        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $unknownEmail->json('errors.email'),
        );
    }

    /**
     * Abilities are derived from permissions, so the API cannot reach a
     * different answer than the web about the same person. A user who may add a
     * category inline while logging an expense can do it from a phone too.
     */
    public function test_a_new_token_carries_the_abilities_the_permissions_justify(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $user->applyRole(RoleName::User);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
        ])->assertOk();

        $abilities = $user->tokens()->first()->abilities;

        $this->assertContains(TokenAbility::ExpensesWrite->value, $abilities);
        $this->assertContains(TokenAbility::CategoriesWrite->value, $abilities);
    }

    public function test_a_token_omits_abilities_the_permissions_do_not_cover(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $user->applyRole(RoleName::User);
        $user->revokePermissionTo(Permission::CategoriesCreate->value);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
        ])->assertOk();

        $abilities = $user->tokens()->first()->abilities;

        $this->assertNotContains(TokenAbility::CategoriesWrite->value, $abilities);
        // Reading them is a separate permission and survives.
        $this->assertContains(TokenAbility::CategoriesRead->value, $abilities);
    }

    public function test_a_client_can_request_a_narrower_token(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
            'abilities' => [TokenAbility::ExpensesRead->value],
        ])->assertOk();

        $this->assertSame(
            [TokenAbility::ExpensesRead->value],
            $user->tokens()->first()->abilities,
        );
    }

    /**
     * The intersection is the whole point: asking for more than your permissions
     * cover must not widen the token.
     */
    public function test_a_client_cannot_request_an_ability_its_permissions_do_not_cover(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $user->applyRole(RoleName::User);
        $user->revokePermissionTo(Permission::CategoriesCreate->value);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
            'abilities' => [
                TokenAbility::ExpensesRead->value,
                TokenAbility::CategoriesWrite->value,
            ],
        ])->assertOk();

        $abilities = $user->tokens()->first()->abilities;

        $this->assertContains(TokenAbility::ExpensesRead->value, $abilities);
        $this->assertNotContains(TokenAbility::CategoriesWrite->value, $abilities);
    }

    public function test_register_creates_a_user_with_the_user_role_and_a_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'phone',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['token', 'user' => ['uuid']]);

        $user = User::where('email', 'sam@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::User->value));
        $this->assertFalse($user->isAdmin());

        // The role is not the point — the permissions it carries are. Asserting
        // hasRole() alone passed happily while register granted no permissions
        // at all, which is how the API shipped issuing tokens that 403'd on
        // every request.
        $this->assertEqualsCanonicalizing(
            Permission::forUser(),
            $user->permissions->pluck('name')->all(),
        );
    }

    /** A token from register has to actually work, not just exist. */
    public function test_the_token_from_register_can_use_the_api(): void
    {
        $category = Category::factory()->create();

        $token = $this->postJson('/api/v1/register', [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'phone',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/expenses', [
            'item' => ['en' => 'Coffee'],
            'price' => 4.5,
            'category_uuid' => $category->uuid,
            'spent_on' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_register_cannot_self_assign_a_role(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'phone',
            'role' => RoleName::Admin->value,
            'roles' => [RoleName::Admin->value],
        ])->assertStatus(201);

        $this->assertFalse(User::where('email', 'sam@example.com')->firstOrFail()->isAdmin());
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone', TokenAbility::defaults($user))->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonMissingPath('data.id');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    /**
     * Logging out on one device must not sign the user out everywhere.
     */
    public function test_logout_revokes_only_the_calling_token(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('phone', TokenAbility::defaults($user))->plainTextToken;
        $user->createToken('laptop', TokenAbility::defaults($user));

        $this->withToken($phone)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('laptop', $user->tokens()->first()->name);

        // The guard caches the resolved user for the lifetime of the test's
        // application instance, so without this the next request would be
        // answered from memory and never re-check the deleted token.
        $this->app['auth']->forgetGuards();

        $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        // The api-login limiter allows 5 per minute per email+IP.
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/login', [
                'email' => 'sam@example.com',
                'password' => 'wrong',
                'device_name' => 'phone',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'wrong',
            'device_name' => 'phone',
        ])->assertStatus(429);
    }
}
