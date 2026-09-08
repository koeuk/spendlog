<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Savings pages: goals with their balances, the ledger behind each, and
 * the rules on what can go in and out.
 */
class SavingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so "today" is not whatever the clock says when the suite runs.
        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    private function goalFor(User $user, array $attributes = []): SavingsGoal
    {
        return SavingsGoal::factory()->for($user)->create(['target_amount' => 500, ...$attributes]);
    }

    public function test_the_page_requires_authentication(): void
    {
        $this->get(route('savings.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_lists_only_the_viewers_goals_with_their_balances(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        $goal = $this->goalFor($me, ['name' => 'Laptop']);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 150, 'saved_on' => '2026-09-01']);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => -50, 'saved_on' => '2026-09-02']);
        $this->goalFor($someoneElse, ['name' => 'Theirs']);

        $response = $this->actingAs($me)->get(route('savings.index'));

        $response->assertOk();
        $props = $this->props($response);

        $this->assertCount(1, $props['goals']);
        $this->assertSame('Laptop', $props['goals'][0]['name']);
        $this->assertEqualsWithDelta(100.0, $props['goals'][0]['saved'], 0.001);
        $this->assertEqualsWithDelta(400.0, $props['goals'][0]['remaining'], 0.001);
        $this->assertSame(20, $props['goals'][0]['percent']);
        $this->assertFalse($props['goals'][0]['reached']);

        $this->assertSame(1, $props['totals']['goals_count']);
        $this->assertEqualsWithDelta(100.0, $props['totals']['total_saved'], 0.001);
        $this->assertEqualsWithDelta(100.0, $props['totals']['saved_this_month'], 0.001);
        $this->assertTrue($props['can']['create']);
    }

    public function test_an_account_without_the_permission_is_refused(): void
    {
        $me = $this->ordinaryUser();
        $me->revokePermissionTo(Permission::SavingsView->value);

        $this->actingAs($me->fresh())->get(route('savings.index'))->assertForbidden();
    }

    public function test_storing_a_goal_lands_on_its_page(): void
    {
        $me = $this->ordinaryUser();

        $response = $this->actingAs($me)->post(route('savings.store'), [
            'name' => 'Emergency fund',
            'target_amount' => '500',
            'deadline' => '2027-01-01',
            'color' => 'teal',
        ]);

        $goal = SavingsGoal::query()->forUser($me->id)->firstOrFail();

        $response->assertRedirect(route('savings.show', $goal));

        $this->assertSame('Emergency fund', $goal->name);
        $this->assertSame('teal', $goal->color->value);
        $this->assertSame('2027-01-01', $goal->deadline->toDateString());
    }

    public function test_a_new_goal_cannot_already_be_overdue(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)
            ->from(route('savings.create'))
            ->post(route('savings.store'), [
                'name' => 'Late',
                'target_amount' => '10',
                'deadline' => '2026-09-07',
            ])
            ->assertRedirect(route('savings.create'))
            ->assertSessionHasErrors('deadline');

        $this->assertDatabaseCount('savings_goals', 0);
    }

    public function test_the_goal_page_shows_the_ledger_newest_first(): void
    {
        $me = $this->ordinaryUser();
        $goal = $this->goalFor($me);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 100, 'saved_on' => '2026-09-01', 'note' => 'First']);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => -30, 'saved_on' => '2026-09-03', 'note' => 'Taken']);

        $props = $this->props($this->actingAs($me)->get(route('savings.show', $goal))->assertOk());

        $this->assertSame(['Taken', 'First'], array_column($props['entries'], 'note'));
        $this->assertSame(['withdraw', 'deposit'], array_column($props['entries'], 'type'));
        // The page speaks in absolute amounts; the sign is the type.
        $this->assertEqualsWithDelta(30.0, $props['entries'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(70.0, $props['goal']['saved'], 0.001);
        $this->assertTrue($props['can']['update']);
    }

    public function test_someone_elses_goal_is_forbidden(): void
    {
        $me = $this->ordinaryUser();
        $theirs = $this->goalFor($this->ordinaryUser());

        $this->actingAs($me)->get(route('savings.show', $theirs))->assertForbidden();
        $this->actingAs($me)->get(route('savings.edit', $theirs))->assertForbidden();
        $this->actingAs($me)->delete(route('savings.destroy', $theirs))->assertForbidden();
        $this->actingAs($me)->post(route('savings.entries.store', $theirs), [
            'type' => 'deposit', 'amount' => '10', 'saved_on' => '2026-09-01',
        ])->assertForbidden();
    }

    public function test_a_deposit_is_added_to_the_ledger_under_the_goals_owner(): void
    {
        $me = $this->ordinaryUser();
        $goal = $this->goalFor($me);

        $this->actingAs($me)
            ->post(route('savings.entries.store', $goal), [
                'type' => 'deposit',
                'amount' => '50',
                'saved_on' => '2026-09-05',
                'note' => 'Leftover',
            ])
            ->assertRedirect(route('savings.show', $goal));

        $this->assertDatabaseHas('savings_entries', [
            'savings_goal_id' => $goal->id,
            'user_id' => $me->id,
            'amount' => '50.0000',
            'saved_on' => '2026-09-05',
            'note' => 'Leftover',
        ]);
    }

    public function test_a_withdrawal_is_stored_negative_and_cannot_exceed_the_balance(): void
    {
        $me = $this->ordinaryUser();
        $goal = $this->goalFor($me);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 40, 'saved_on' => '2026-09-01']);

        $this->actingAs($me)
            ->from(route('savings.entries.create', $goal))
            ->post(route('savings.entries.store', $goal), [
                'type' => 'withdraw', 'amount' => '50', 'saved_on' => '2026-09-05',
            ])
            ->assertRedirect(route('savings.entries.create', $goal))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('savings_entries', 1);

        $this->actingAs($me)
            ->post(route('savings.entries.store', $goal), [
                'type' => 'withdraw', 'amount' => '40', 'saved_on' => '2026-09-05',
            ])
            ->assertRedirect(route('savings.show', $goal));

        $this->assertDatabaseHas('savings_entries', ['savings_goal_id' => $goal->id, 'amount' => '-40.0000']);
    }

    public function test_deleting_an_entry_is_scoped_to_its_goal(): void
    {
        $me = $this->ordinaryUser();
        $goal = $this->goalFor($me);
        $otherGoal = $this->goalFor($me);
        $entry = SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 20]);

        // Right entry, wrong goal in the URL: a 404, not a delete.
        $this->actingAs($me)
            ->delete(route('savings.entries.destroy', ['goal' => $otherGoal, 'entry' => $entry]))
            ->assertNotFound();

        $this->assertDatabaseHas('savings_entries', ['id' => $entry->id]);

        $this->actingAs($me)
            ->delete(route('savings.entries.destroy', ['goal' => $goal, 'entry' => $entry]))
            ->assertRedirect();

        $this->assertDatabaseMissing('savings_entries', ['id' => $entry->id]);
    }

    public function test_deleting_a_goal_takes_its_ledger_with_it(): void
    {
        $me = $this->ordinaryUser();
        $goal = $this->goalFor($me);
        SavingsEntry::factory()->for($goal, 'goal')->create(['amount' => 20]);

        $this->actingAs($me)->delete(route('savings.destroy', $goal))->assertRedirect(route('savings.index'));

        $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
        $this->assertDatabaseCount('savings_entries', 0);
    }
}
