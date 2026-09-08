<?php

namespace Tests\Feature\Settings;

use App\Enums\RoleName;
use App\Models\Income;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings → Activity, the web face of the activity log. The write side is
 * covered by the API suite; this is the page and its scope rule.
 */
class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function user(RoleName $role = RoleName::User): User
    {
        $user = User::factory()->create();
        $user->applyRole($role);

        return $user;
    }

    public function test_any_user_can_open_their_own_activity(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user);
        Income::factory()->for($user)->create(['source' => 'Salary', 'amount' => 100]);

        $this->actingAs($other);
        Income::factory()->for($other)->create(['source' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Activity')
                ->where('scope', 'mine')
                ->where('can.all', false)
                ->has('entries', 1)
                ->where('entries.0.label', 'Salary · $100.00')
                ->where('entries.0.action', 'created')
                ->where('entries.0.subject', 'income')
            );
    }

    public function test_an_admin_can_see_everyones_activity(): void
    {
        $admin = $this->user(RoleName::Admin);
        $user = $this->user();

        $this->actingAs($user);
        Income::factory()->for($user)->create(['source' => 'Theirs']);

        $this->actingAs($admin)
            ->get(route('activity.index', ['scope' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'all')
                ->where('can.all', true)
                ->has('entries', 1)
                ->where('entries.0.user', $user->name)
            );
    }

    public function test_a_normal_user_cannot_widen_the_scope(): void
    {
        $this->actingAs($this->user())
            ->get(route('activity.index', ['scope' => 'all']))
            ->assertForbidden();
    }

    public function test_the_page_requires_sign_in(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
    }
}
