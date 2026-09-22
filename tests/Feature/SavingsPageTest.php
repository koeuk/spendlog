<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\SavingsEntry;
use App\Models\SavingsPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Savings page: one month's plan, the money that actually moved against
 * it, and the rules on what can go in and out.
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

    /** @return array<string, mixed> */
    private function entryPayload(string $type, string $amount, array $overrides = []): array
    {
        return [
            'type' => $type,
            'amount' => $amount,
            'saved_on' => '2026-09-05',
            ...$overrides,
        ];
    }

    public function test_the_page_requires_authentication(): void
    {
        $this->get(route('savings.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_shows_the_months_plan_entries_and_the_all_time_balance(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        SavingsPlan::factory()->for($me)->forMonth('2026-09')->create(['amount' => 100]);
        SavingsEntry::factory()->for($me)->create(['amount' => 200, 'saved_on' => '2026-08-10']);
        SavingsEntry::factory()->for($me)->create(['amount' => 80, 'saved_on' => '2026-09-01']);
        SavingsEntry::factory()->for($me)->withdrawal(20)->create(['saved_on' => '2026-09-02']);
        SavingsEntry::factory()->for($someoneElse)->create(['amount' => 999, 'saved_on' => '2026-09-03']);

        $response = $this->actingAs($me)->get(route('savings.index'));

        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('2026-09', $props['month']);
        $this->assertEqualsWithDelta(100.0, $props['summary']['planned'], 0.001);
        $this->assertEqualsWithDelta(60.0, $props['summary']['saved_this_month'], 0.001);
        // All time, August included; the other account's money is not.
        $this->assertEqualsWithDelta(260.0, $props['summary']['total_saved'], 0.001);
        $this->assertSame(60, $props['summary']['percent']);
        $this->assertSame('ok', $props['summary']['status']);

        // The month's ledger only, newest first.
        $this->assertCount(2, $props['entries']);
        $this->assertSame('2026-09-02', $props['entries'][0]['saved_on']);
        $this->assertSame('withdraw', $props['entries'][0]['type']);
        $this->assertEqualsWithDelta(20.0, $props['entries'][0]['amount'], 0.001);
        $this->assertSame('deposit', $props['entries'][1]['type']);

        $this->assertEqualsWithDelta(100.0, $props['plan']['amount'], 0.001);
        $this->assertTrue($props['can']['create']);
    }

    public function test_another_month_can_be_opened_from_the_query_string(): void
    {
        $me = $this->ordinaryUser();
        SavingsEntry::factory()->for($me)->create(['amount' => 40, 'saved_on' => '2026-08-10']);

        $props = $this->props($this->actingAs($me)->get(route('savings.index', ['month' => '2026-08'])));

        $this->assertSame('2026-08', $props['month']);
        $this->assertSame('2026-07', $props['prev_month']);
        $this->assertSame('2026-09', $props['next_month']);
        $this->assertCount(1, $props['entries']);
        // No plan for August: null rather than a zero row.
        $this->assertNull($props['plan']);
        $this->assertEqualsWithDelta(0.0, $props['summary']['planned'], 0.001);
    }

    public function test_an_account_without_the_permission_is_refused(): void
    {
        $me = $this->ordinaryUser();
        $me->revokePermissionTo(Permission::SavingsView->value);

        $this->actingAs($me->fresh())->get(route('savings.index'))->assertForbidden();
    }

    public function test_setting_the_plan_twice_updates_the_same_row(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)
            ->post(route('savings.plan.store'), ['month' => '2026-09', 'amount' => '100'])
            ->assertRedirect();

        $this->actingAs($me)
            ->post(route('savings.plan.store'), ['month' => '2026-09', 'amount' => '250'])
            ->assertRedirect();

        $this->assertSame(1, SavingsPlan::query()->forUser($me->id)->count());
        $this->assertEqualsWithDelta(
            250.0,
            (float) SavingsPlan::query()->forUser($me->id)->firstOrFail()->amount,
            0.001,
        );
    }

    public function test_a_riel_plan_is_stored_in_dollars(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)->post(route('savings.plan.store'), [
            'month' => '2026-09',
            'amount' => '410000',
            'currency' => 'KHR',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(
            100.0,
            (float) SavingsPlan::query()->forUser($me->id)->firstOrFail()->amount,
            0.01,
        );
    }

    public function test_clearing_the_plan_leaves_the_money_alone(): void
    {
        $me = $this->ordinaryUser();
        $plan = SavingsPlan::factory()->for($me)->forMonth('2026-09')->create();
        SavingsEntry::factory()->for($me)->create(['amount' => 60, 'saved_on' => '2026-09-05']);

        $this->actingAs($me)->delete(route('savings.plan.destroy', $plan))->assertRedirect();

        $this->assertDatabaseMissing('savings_plans', ['id' => $plan->id]);
        $this->assertSame(1, SavingsEntry::query()->forUser($me->id)->count());
    }

    public function test_someone_elses_plan_or_entry_cannot_be_touched(): void
    {
        $me = $this->ordinaryUser();
        $theirs = $this->ordinaryUser();
        $plan = SavingsPlan::factory()->for($theirs)->create();
        $entry = SavingsEntry::factory()->for($theirs)->create(['amount' => 25]);

        $this->actingAs($me)->delete(route('savings.plan.destroy', $plan))->assertForbidden();
        $this->actingAs($me)->get(route('savings.entries.edit', $entry))->assertForbidden();
        $this->actingAs($me)->delete(route('savings.entries.destroy', $entry))->assertForbidden();

        $this->assertDatabaseHas('savings_plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('savings_entries', ['id' => $entry->id]);
    }

    public function test_a_deposit_and_a_withdrawal_are_recorded_with_their_sign(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)
            ->post(route('savings.entries.store'), $this->entryPayload('deposit', '150'))
            ->assertRedirect(route('savings.index', ['month' => '2026-09']));

        $this->actingAs($me)
            ->post(route('savings.entries.store'), $this->entryPayload('withdraw', '50'))
            ->assertRedirect();

        $this->assertDatabaseHas('savings_entries', ['user_id' => $me->id, 'amount' => 150]);
        $this->assertDatabaseHas('savings_entries', ['user_id' => $me->id, 'amount' => -50]);
    }

    public function test_a_withdrawal_is_capped_by_the_all_time_balance(): void
    {
        $me = $this->ordinaryUser();
        // Saved in August; still there to take out in September.
        SavingsEntry::factory()->for($me)->create(['amount' => 40, 'saved_on' => '2026-08-10']);

        $this->actingAs($me)
            ->post(route('savings.entries.store'), $this->entryPayload('withdraw', '40.01'))
            ->assertSessionHasErrors('amount');

        $this->actingAs($me)
            ->post(route('savings.entries.store'), $this->entryPayload('withdraw', '40'))
            ->assertRedirect();

        $this->assertSame(2, SavingsEntry::query()->forUser($me->id)->count());
    }

    public function test_an_entry_can_be_edited_and_deleted(): void
    {
        $me = $this->ordinaryUser();
        $entry = SavingsEntry::factory()->for($me)->create(['amount' => 60, 'saved_on' => '2026-09-02']);

        $this->actingAs($me)->get(route('savings.entries.edit', $entry))->assertOk();

        $this->actingAs($me)
            ->patch(route('savings.entries.update', $entry), $this->entryPayload('deposit', '90', [
                'saved_on' => '2026-09-06',
                'note' => 'Payday',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('savings_entries', [
            'id' => $entry->id,
            'amount' => 90,
            'saved_on' => '2026-09-06',
            'note' => 'Payday',
        ]);

        $this->actingAs($me)->delete(route('savings.entries.destroy', $entry))->assertRedirect();
        $this->assertDatabaseMissing('savings_entries', ['id' => $entry->id]);
    }
}
