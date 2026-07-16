<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
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

    public function test_a_new_token_excludes_categories_write_by_default(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $user->applyRole(RoleName::Admin);

        $this->postJson('/api/v1/login', [
            'email' => 'sam@example.com',
            'password' => 'password',
            'device_name' => 'phone',
        ])->assertOk();

        $abilities = $user->tokens()->first()->abilities;

        $this->assertNotContains(TokenAbility::CategoriesWrite->value, $abilities);
        $this->assertContains(TokenAbility::ExpensesWrite->value, $abilities);
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
     * The intersection is the whole point: asking for more than you may have
     * must not widen the token.
     */
    public function test_a_non_admin_cannot_request_categories_write(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);
        $user->applyRole(RoleName::User);

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
