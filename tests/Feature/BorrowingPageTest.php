<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Borrowing page: own rows only, what is still owed derived from the
 * ledger, and the rules on what a repayment may be.
 */
class BorrowingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so "today" is not whatever the clock says when the suite runs.
        Carbon::setTestNow('2026-09-14 10:00:00');
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

    /** @return array<int, string> the lenders in list order */
    private function lenders(array $props): array
    {
        return collect($props['borrowings'])->pluck('lender')->all();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'lender' => 'Mom',
            'lender_type' => 'family',
            'amount' => '200',
            'borrowed_on' => '2026-09-01',
            ...$overrides,
        ];
    }

    public function test_the_page_requires_authentication(): void
    {
        $this->get(route('borrowings.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_lists_only_the_viewers_own_borrowing_still_owed_first(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        $settled = Borrowing::factory()->for($me)->create(['lender' => 'Settled', 'amount' => 50, 'borrowed_on' => '2026-09-10']);
        BorrowingRepayment::factory()->for($settled)->create(['amount' => 50]);
        Borrowing::factory()->for($me)->create(['lender' => 'Older', 'amount' => 100, 'borrowed_on' => '2026-08-01']);
        Borrowing::factory()->for($me)->create(['lender' => 'Newer', 'amount' => 100, 'borrowed_on' => '2026-09-05']);
        Borrowing::factory()->for($someoneElse)->create(['lender' => 'Theirs']);

        $response = $this->actingAs($me)->get(route('borrowings.index'));

        $response->assertOk();
        $props = $this->props($response);

        // Open rows first (newest borrowed), the settled one last.
        $this->assertSame(['Newer', 'Older', 'Settled'], $this->lenders($props));
        $this->assertTrue($props['borrowings'][2]['settled']);
        $this->assertEquals(200, $props['summary']['outstanding']);
        $this->assertSame(2, $props['summary']['open_count']);
        $this->assertSame(1, $props['summary']['settled_count']);
    }

    public function test_the_page_filters_by_status_type_and_lender(): void
    {
        $me = $this->ordinaryUser();

        $paid = Borrowing::factory()->for($me)->create(['lender' => 'Sokha', 'lender_type' => 'friend', 'amount' => 30]);
        BorrowingRepayment::factory()->for($paid)->create(['amount' => 30]);
        Borrowing::factory()->for($me)->create(['lender' => 'Mom', 'lender_type' => 'family', 'amount' => 100]);
        Borrowing::factory()->for($me)->create(['lender' => 'ABA Bank', 'lender_type' => 'bank', 'amount' => 500]);

        $open = $this->props($this->actingAs($me)->get(route('borrowings.index', ['status' => 'open'])));
        $this->assertEqualsCanonicalizing(['Mom', 'ABA Bank'], $this->lenders($open));

        $settled = $this->props($this->actingAs($me)->get(route('borrowings.index', ['status' => 'settled'])));
        $this->assertSame(['Sokha'], $this->lenders($settled));

        $byType = $this->props($this->actingAs($me)->get(route('borrowings.index', ['filter' => ['type' => 'bank']])));
        $this->assertSame(['ABA Bank'], $this->lenders($byType));

        $byName = $this->props($this->actingAs($me)->get(route('borrowings.index', ['filter' => ['lender' => 'mo']])));
        $this->assertSame(['Mom'], $this->lenders($byName));
    }

    public function test_overdue_is_derived_from_the_due_date_and_the_ledger(): void
    {
        $me = $this->ordinaryUser();

        Borrowing::factory()->for($me)->create(['lender' => 'Late', 'amount' => 100, 'due_on' => '2026-09-01']);
        $paidLate = Borrowing::factory()->for($me)->create(['lender' => 'Paid', 'amount' => 100, 'due_on' => '2026-09-01']);
        BorrowingRepayment::factory()->for($paidLate)->create(['amount' => 100]);
        Borrowing::factory()->for($me)->create(['lender' => 'Future', 'amount' => 100, 'due_on' => '2026-10-01']);

        $props = $this->props($this->actingAs($me)->get(route('borrowings.index')));

        $rows = collect($props['borrowings'])->keyBy('lender');
        $this->assertTrue($rows['Late']['overdue']);
        // Paid back: a passed due date no longer means anything.
        $this->assertFalse($rows['Paid']['overdue']);
        $this->assertFalse($rows['Future']['overdue']);
        $this->assertSame(1, $props['summary']['overdue_count']);
    }

    public function test_an_account_without_the_permission_is_refused(): void
    {
        $me = $this->ordinaryUser();
        $me->revokePermissionTo(Permission::BorrowingsView->value);

        $this->actingAs($me->fresh())->get(route('borrowings.index'))->assertForbidden();
    }

    public function test_storing_creates_the_row_for_the_signed_in_user(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)
            ->post(route('borrowings.store'), $this->payload(['due_on' => '2026-12-01', 'note' => 'Motorbike']))
            ->assertRedirect(route('borrowings.index'));

        $this->assertDatabaseHas('borrowings', [
            'user_id' => $me->id,
            'lender' => 'Mom',
            'lender_type' => 'family',
            'amount' => '200.0000',
            'borrowed_on' => '2026-09-01',
            'due_on' => '2026-12-01',
            'note' => 'Motorbike',
        ]);
    }

    public function test_a_riel_amount_is_stored_in_dollars(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)->post(route('borrowings.store'), $this->payload(['amount' => '41000', 'currency' => 'KHR']));

        $this->assertEqualsWithDelta(10.0, (float) Borrowing::query()->forUser($me->id)->value('amount'), 0.01);
    }

    public function test_a_future_borrowing_a_bad_type_and_a_due_date_before_it_are_rejected(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload(['borrowed_on' => '2026-09-15']))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('borrowed_on');

        $this->actingAs($me)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload(['lender_type' => 'stranger']))
            ->assertSessionHasErrors('lender_type');

        $this->actingAs($me)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload(['due_on' => '2026-08-31']))
            ->assertSessionHasErrors('due_on');

        $this->assertDatabaseCount('borrowings', 0);
    }

    public function test_the_form_offers_the_viewers_own_lenders_most_used_first(): void
    {
        $me = $this->ordinaryUser();
        Borrowing::factory()->for($me)->count(2)->create(['lender' => 'Mom']);
        Borrowing::factory()->for($me)->create(['lender' => 'Sokha']);
        Borrowing::factory()->create(['lender' => 'Theirs']);

        $props = $this->props($this->actingAs($me)->get(route('borrowings.create'))->assertOk());

        $this->assertSame(['Mom', 'Sokha'], $props['lenders']);
        $this->assertSame('friend', $props['lender_types'][0]['value']);
    }

    public function test_a_repayment_reduces_what_is_owed_and_cannot_exceed_it(): void
    {
        $me = $this->ordinaryUser();
        $borrowing = Borrowing::factory()->for($me)->create(['amount' => 100, 'borrowed_on' => '2026-09-01']);

        $this->actingAs($me)
            ->post(route('borrowings.repayments.store', $borrowing), ['amount' => '60', 'paid_on' => '2026-09-10'])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->assertDatabaseHas('borrowing_repayments', [
            'borrowing_id' => $borrowing->id,
            'user_id' => $me->id,
            'amount' => '60.0000',
        ]);

        $props = $this->props($this->actingAs($me)->get(route('borrowings.show', $borrowing)));
        $this->assertEquals(60, $props['borrowing']['repaid']);
        $this->assertEquals(40, $props['borrowing']['remaining']);
        $this->assertFalse($props['borrowing']['settled']);
        $this->assertCount(1, $props['repayments']);

        // More than the $40 left is refused, and nothing is written.
        $this->actingAs($me)
            ->from(route('borrowings.repayments.create', $borrowing))
            ->post(route('borrowings.repayments.store', $borrowing), ['amount' => '40.01', 'paid_on' => '2026-09-11'])
            ->assertRedirect(route('borrowings.repayments.create', $borrowing))
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, BorrowingRepayment::count());

        // Exactly what is left settles it.
        $this->actingAs($me)
            ->post(route('borrowings.repayments.store', $borrowing), ['amount' => '40', 'paid_on' => '2026-09-11'])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->assertTrue($borrowing->fresh()->isSettled());
    }

    public function test_a_repayment_cannot_predate_the_borrowing_or_sit_in_the_future(): void
    {
        $me = $this->ordinaryUser();
        $borrowing = Borrowing::factory()->for($me)->create(['amount' => 100, 'borrowed_on' => '2026-09-01']);

        $this->actingAs($me)
            ->from(route('borrowings.repayments.create', $borrowing))
            ->post(route('borrowings.repayments.store', $borrowing), ['amount' => '10', 'paid_on' => '2026-08-31'])
            ->assertSessionHasErrors('paid_on');

        $this->actingAs($me)
            ->from(route('borrowings.repayments.create', $borrowing))
            ->post(route('borrowings.repayments.store', $borrowing), ['amount' => '10', 'paid_on' => '2026-09-15'])
            ->assertSessionHasErrors('paid_on');

        $this->assertDatabaseCount('borrowing_repayments', 0);
    }

    public function test_editing_cannot_take_the_amount_below_what_is_repaid(): void
    {
        $me = $this->ordinaryUser();
        $borrowing = Borrowing::factory()->for($me)->create(['amount' => 100, 'borrowed_on' => '2026-09-01']);
        BorrowingRepayment::factory()->for($borrowing)->create(['amount' => 60]);

        $this->actingAs($me)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), $this->payload(['amount' => '50']))
            ->assertRedirect(route('borrowings.edit', $borrowing))
            ->assertSessionHasErrors('amount');

        $this->assertSame('100.0000', $borrowing->fresh()->amount);

        $this->actingAs($me)
            ->put(route('borrowings.update', $borrowing), $this->payload(['amount' => '60', 'lender' => 'Dad']))
            ->assertRedirect(route('borrowings.show', $borrowing));

        $fresh = $borrowing->fresh();
        $this->assertSame('Dad', $fresh->lender);
        $this->assertTrue($fresh->isSettled());
    }

    public function test_deleting_a_repayment_reopens_the_borrowing_and_is_scoped_to_it(): void
    {
        $me = $this->ordinaryUser();
        $borrowing = Borrowing::factory()->for($me)->create(['amount' => 100]);
        $repayment = BorrowingRepayment::factory()->for($borrowing)->create(['amount' => 100]);
        $other = Borrowing::factory()->for($me)->create(['amount' => 100]);

        $this->assertTrue($borrowing->fresh()->isSettled());

        // The right repayment under the wrong borrowing is not found.
        $this->actingAs($me)
            ->delete(route('borrowings.repayments.destroy', ['borrowing' => $other, 'repayment' => $repayment]))
            ->assertNotFound();

        $this->actingAs($me)
            ->delete(route('borrowings.repayments.destroy', ['borrowing' => $borrowing, 'repayment' => $repayment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('borrowing_repayments', ['id' => $repayment->id]);
        $this->assertFalse($borrowing->fresh()->isSettled());
    }

    public function test_updating_repaying_and_deleting_are_limited_to_the_owner(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        $mine = Borrowing::factory()->for($me)->create(['lender' => 'Mom']);
        $theirs = Borrowing::factory()->for($someoneElse)->create(['lender' => 'Theirs', 'borrowed_on' => '2026-09-01']);

        $this->actingAs($me)->get(route('borrowings.show', $theirs))->assertForbidden();
        $this->actingAs($me)->get(route('borrowings.edit', $theirs))->assertForbidden();
        $this->actingAs($me)->put(route('borrowings.update', $theirs), $this->payload(['lender' => 'Hijacked']))->assertForbidden();
        $this->actingAs($me)->get(route('borrowings.repayments.create', $theirs))->assertForbidden();
        $this->actingAs($me)
            ->post(route('borrowings.repayments.store', $theirs), ['amount' => '1', 'paid_on' => '2026-09-10'])
            ->assertForbidden();
        $this->actingAs($me)->delete(route('borrowings.destroy', $theirs))->assertForbidden();

        $this->actingAs($me)->delete(route('borrowings.destroy', $mine))->assertRedirect(route('borrowings.index'));

        $this->assertDatabaseMissing('borrowings', ['id' => $mine->id]);
        $this->assertDatabaseHas('borrowings', ['id' => $theirs->id, 'lender' => 'Theirs']);
        $this->assertDatabaseCount('borrowing_repayments', 0);
    }

    public function test_deleting_a_borrowing_takes_its_repayments_with_it(): void
    {
        $me = $this->ordinaryUser();
        $borrowing = Borrowing::factory()->for($me)->create(['amount' => 100]);
        BorrowingRepayment::factory()->for($borrowing)->count(2)->create(['amount' => 10]);

        $this->actingAs($me)->delete(route('borrowings.destroy', $borrowing))->assertRedirect();

        $this->assertDatabaseCount('borrowing_repayments', 0);
    }
}
