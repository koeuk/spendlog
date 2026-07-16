<?php

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * dashboard.view is a revocable permission, and its own description promises
 * that revoking it lands the user on expenses. Nothing implemented that, so the
 * login redirected into a 403 with no way out.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    public function test_login_lands_on_the_dashboard_by_default(): void
    {
        $user = $this->user();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_falls_back_to_expenses_when_the_dashboard_is_revoked(): void
    {
        $user = $this->user();
        $user->revokePermissionTo(Permission::DashboardView->value);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('expenses.index', absolute: false));
    }

    public function test_landing_page_walks_down_to_whatever_remains(): void
    {
        $user = $this->user();
        $user->revokePermissionTo(Permission::DashboardView->value);
        $user->revokePermissionTo(Permission::ExpensesView->value);
        $user->revokePermissionTo(Permission::BudgetsView->value);

        $this->assertSame('reports.index', $user->fresh()->homeRoute());
    }

    public function test_an_account_with_no_view_permissions_still_lands_somewhere_usable(): void
    {
        $user = $this->user();

        foreach ([
            Permission::DashboardView,
            Permission::ExpensesView,
            Permission::BudgetsView,
            Permission::ReportsView,
            Permission::CategoriesView,
        ] as $permission) {
            $user->revokePermissionTo($permission->value);
        }

        $this->assertSame('profile.edit', $user->fresh()->homeRoute());

        // And it must actually be reachable, not another 403.
        $this->actingAs($user->fresh())->get(route('profile.edit'))->assertOk();
    }

    public function test_the_root_doorway_does_not_send_a_user_to_a_forbidden_dashboard(): void
    {
        $user = $this->user();
        $user->revokePermissionTo(Permission::DashboardView->value);

        $this->actingAs($user->fresh())
            ->get('/')
            ->assertRedirect(route('expenses.index', absolute: false));
    }

    /**
     * The hole intended() leaves: a bookmarked /dashboard is stashed as the
     * intended URL while the user is a guest, and replayed after login — when
     * the permission check finally applies.
     */
    public function test_a_bookmarked_dashboard_does_not_strand_a_user_who_cannot_view_it(): void
    {
        $user = $this->user();
        $user->revokePermissionTo(Permission::DashboardView->value);

        // Guest hits the bookmark: auth middleware stashes it and bounces to login.
        $this->get('/dashboard')->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('expenses.index', absolute: false));
    }

    /**
     * The guard above must not cost us intended() itself: a bookmark the user
     * *can* open still has to survive the login.
     */
    public function test_a_bookmarked_page_the_user_may_open_is_still_honoured(): void
    {
        $user = $this->user();

        $this->get('/budgets')->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('budgets.index', absolute: false));
    }

    public function test_a_bookmarked_page_outside_the_permission_map_is_still_honoured(): void
    {
        $user = $this->user();

        $this->get('/settings/profile')->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('profile.edit', absolute: false));
    }
}
