<?php

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * Registering has to grant the ordinary user permissions, not just create the
     * row. It once did only the latter, and because a role grants nothing at run
     * time, every account that signed up could not open a single page.
     */
    public function test_registering_grants_the_ordinary_user_permissions(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->sole();

        $this->assertTrue($user->hasRole(RoleName::User->value));
        $this->assertEqualsCanonicalizing(
            Permission::forUser(),
            $user->permissions->pluck('name')->all(),
        );
    }

    /**
     * The point of the permissions: the app is actually usable afterwards.
     *
     * Verified first, because the dashboard sits behind the 'verified'
     * middleware — otherwise this would redirect for a reason unrelated to
     * permissions and pass just as happily with none at all.
     */
    public function test_a_registered_user_can_use_the_app_once_verified(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->sole();
        $user->markEmailAsVerified();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('expenses.index'))->assertOk();
        $this->actingAs($user)->get(route('budgets.index'))->assertOk();
    }
}
