<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Who the Everyone view is actually open to.
 *
 * The scope comes off the query string, so it is not enough that the toggle is
 * hidden — hiding a button is not a permission. Someone can type ?scope=all.
 * These tests go at the URL directly, which is the only thing that proves it.
 */
class ExpenseScopeTest extends TestCase
{
    use RefreshDatabase;

    private function ordinaryUser(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        preg_match('/data-page="([^"]*)"/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches, 'No Inertia data-page attribute in the response.');

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true)['props'];
    }

    /** @return array<int, string> every item name across the grouped days */
    private function items(array $props): array
    {
        return collect($props['days'])
            ->flatMap(fn (array $day) => $day['expenses'])
            ->pluck('item')
            ->all();
    }

    /**
     * The default set deliberately excludes expenses.view_all, so this is the
     * shape every self-registered account has.
     */
    public function test_an_ordinary_user_does_not_hold_view_all(): void
    {
        $this->assertNotContains(
            Permission::ExpensesViewAll->value,
            Permission::forUser(),
        );

        $this->assertFalse(
            $this->ordinaryUser()->hasPermissionTo(Permission::ExpensesViewAll->value),
        );
    }

    public function test_the_everyone_toggle_is_not_offered_to_an_ordinary_user(): void
    {
        $response = $this->actingAs($this->ordinaryUser())->get(route('expenses.index'));

        $response->assertOk();
        $props = $this->props($response);

        // What the Vue page hides the toggle and the user filter on.
        $this->assertFalse($props['can']['view_all']);
        $this->assertSame([], $props['users']);
    }

    /** Typing the URL by hand must not do what the missing button would have. */
    public function test_an_ordinary_user_asking_for_scope_all_still_only_sees_their_own(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        Expense::factory()->for($me)->create(['item' => ['en' => 'My coffee']]);
        Expense::factory()->for($someoneElse)->create(['item' => ['en' => 'Their noodles']]);

        $response = $this->actingAs($me)->get(route('expenses.index', ['scope' => 'all']));

        $response->assertOk();
        $props = $this->props($response);

        $items = $this->items($props);
        $this->assertContains('My coffee', $items);
        $this->assertNotContains('Their noodles', $items);

        // Forced back, so the page cannot render itself as the Everyone view.
        $this->assertSame('mine', $props['scope']);
        $this->assertFalse($props['can']['view_all']);
        $this->assertSame([], $props['users']);
    }

    /**
     * The user filter is the quieter way in: it is a whereHas on user, so if it
     * survived for a non-admin it would leak rows the scope check just denied.
     *
     * It is not merely ignored — the filter is never registered for a non-admin,
     * so QueryBuilder rejects the request with InvalidFilterQuery (400). Pinned
     * as a 400 rather than a 200 because that is what actually happens, and a
     * test claiming otherwise would be describing a controller we do not have.
     */
    public function test_an_ordinary_user_filtering_by_user_is_refused_outright(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        Expense::factory()->for($someoneElse)->create(['item' => ['en' => 'Their noodles']]);

        $this->actingAs($me)
            ->get(route('expenses.index', [
                'scope' => 'all',
                'filter' => ['user' => $someoneElse->uuid],
            ]))
            ->assertStatus(400);
    }

    /** The same filter is a normal part of the page once view_all is held. */
    public function test_the_user_filter_works_for_someone_holding_view_all(): void
    {
        $me = $this->ordinaryUser();
        $me->givePermissionTo(Permission::ExpensesViewAll->value);

        $someoneElse = $this->ordinaryUser();

        Expense::factory()->for($me)->create(['item' => ['en' => 'My coffee']]);
        Expense::factory()->for($someoneElse)->create(['item' => ['en' => 'Their noodles']]);

        $response = $this->actingAs($me)->get(route('expenses.index', [
            'scope' => 'all',
            'filter' => ['user' => $someoneElse->uuid],
        ]));

        $response->assertOk();

        $items = $this->items($this->props($response));
        $this->assertContains('Their noodles', $items);
        $this->assertNotContains('My coffee', $items);
    }

    /** The permission, not the role: granting it to a plain user must work. */
    public function test_a_user_granted_view_all_sees_everyone(): void
    {
        $me = $this->ordinaryUser();
        $me->givePermissionTo(Permission::ExpensesViewAll->value);

        $someoneElse = $this->ordinaryUser();

        Expense::factory()->for($me)->create(['item' => ['en' => 'My coffee']]);
        Expense::factory()->for($someoneElse)->create(['item' => ['en' => 'Their noodles']]);

        $response = $this->actingAs($me)->get(route('expenses.index', ['scope' => 'all']));

        $response->assertOk();
        $props = $this->props($response);

        $this->assertContains('Their noodles', $this->items($props));
        $this->assertSame('all', $props['scope']);
        $this->assertTrue($props['can']['view_all']);
    }

    /** Revoking it has to close the view again, or the checkbox is decoration. */
    public function test_revoking_view_all_closes_the_everyone_view_again(): void
    {
        $me = $this->ordinaryUser();
        $me->givePermissionTo(Permission::ExpensesViewAll->value);
        $someoneElse = $this->ordinaryUser();

        Expense::factory()->for($someoneElse)->create(['item' => ['en' => 'Their noodles']]);

        $me->revokePermissionTo(Permission::ExpensesViewAll->value);

        $response = $this->actingAs($me->fresh())->get(route('expenses.index', ['scope' => 'all']));

        $response->assertOk();
        $this->assertNotContains('Their noodles', $this->items($this->props($response)));
    }
}
