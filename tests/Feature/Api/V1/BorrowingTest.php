<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Permission;
use App\Enums\TokenAbility;
use App\Models\AppSetting;
use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingTest extends TestCase
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

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/borrowings')->assertUnauthorized();
    }

    public function test_index_returns_only_the_callers_borrowing_still_owed_first(): void
    {
        $user = User::factory()->create();
        $settled = Borrowing::factory()->for($user)->create(['lender' => 'Settled', 'amount' => 50, 'borrowed_on' => '2026-09-10']);
        BorrowingRepayment::factory()->for($settled)->create(['amount' => 50]);
        Borrowing::factory()->for($user)->create(['lender' => 'Older', 'amount' => 100, 'borrowed_on' => '2026-08-01']);
        Borrowing::factory()->for($user)->create(['lender' => 'Newer', 'amount' => 100, 'borrowed_on' => '2026-09-05']);
        Borrowing::factory()->create(['lender' => 'Theirs']);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $response = $this->getJson('/api/v1/borrowings')->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(['Newer', 'Older', 'Settled'], $response->json('data.*.lender'));
        $this->assertSame('100.00', $response->json('data.0.remaining'));
        $this->assertTrue($response->json('data.2.settled'));
        $this->assertSame(1, $response->json('data.2.repayments_count'));
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
        $this->assertArrayNotHasKey('repayments', $response->json('data.0'));
    }

    public function test_index_filters_by_status_type_and_lender(): void
    {
        $user = User::factory()->create();
        $paid = Borrowing::factory()->for($user)->create(['lender' => 'Sokha', 'lender_type' => 'friend', 'amount' => 30]);
        BorrowingRepayment::factory()->for($paid)->create(['amount' => 30]);
        Borrowing::factory()->for($user)->create(['lender' => 'Mom', 'lender_type' => 'family', 'amount' => 100]);
        Borrowing::factory()->for($user)->create(['lender' => 'ABA Bank', 'lender_type' => 'bank', 'amount' => 500]);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $this->getJson('/api/v1/borrowings?status=open')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/borrowings?status=settled')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lender', 'Sokha');
        $this->getJson('/api/v1/borrowings?filter[type]=bank')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lender', 'ABA Bank');
        $this->getJson('/api/v1/borrowings?filter[lender]=mo')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_summary_reports_what_is_owed_and_to_whom(): void
    {
        $user = User::factory()->create();
        $mom = Borrowing::factory()->for($user)->create(['lender_type' => 'family', 'amount' => 200, 'due_on' => '2026-09-01']);
        BorrowingRepayment::factory()->for($mom)->create(['amount' => 50]);
        Borrowing::factory()->for($user)->create(['lender_type' => 'friend', 'amount' => 120, 'due_on' => null]);
        $paid = Borrowing::factory()->for($user)->create(['lender_type' => 'bank', 'amount' => 250]);
        BorrowingRepayment::factory()->for($paid)->create(['amount' => 250]);
        // Someone else's does not count.
        Borrowing::factory()->create(['amount' => 9999]);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $response = $this->getJson('/api/v1/borrowings/summary')->assertOk();

        $this->assertSame('270.00', $response->json('data.outstanding'));
        $this->assertSame('570.00', $response->json('data.borrowed'));
        $this->assertSame('300.00', $response->json('data.repaid'));
        $this->assertSame(2, $response->json('data.open_count'));
        $this->assertSame(1, $response->json('data.settled_count'));
        $this->assertSame(1, $response->json('data.overdue_count'));
        $this->assertSame([
            ['lender_type' => 'family', 'label' => 'Family', 'outstanding' => '150.00', 'count' => 1],
            ['lender_type' => 'friend', 'label' => 'Friend', 'outstanding' => '120.00', 'count' => 1],
        ], $response->json('data.by_lender_type'));
    }

    public function test_lenders_lists_the_callers_names_and_the_types(): void
    {
        $user = User::factory()->create();
        Borrowing::factory()->for($user)->count(2)->create(['lender' => 'Mom']);
        Borrowing::factory()->for($user)->create(['lender' => 'Sokha']);
        Borrowing::factory()->create(['lender' => 'Theirs']);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $response = $this->getJson('/api/v1/borrowings/lenders')->assertOk();

        $this->assertSame(['Mom', 'Sokha'], $response->json('data.lenders'));
        $this->assertSame(['friend', 'family', 'bank', 'employer', 'other'], $response->json('data.types.*.value'));
    }

    public function test_a_read_only_token_cannot_record_a_borrowing(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $this->postJson('/api/v1/borrowings', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid ability provided.');
    }

    public function test_store_records_a_borrowing_for_the_token_owner(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BorrowingsWrite->value]);

        $response = $this->postJson('/api/v1/borrowings', $this->payload(['due_on' => '2026-12-01', 'note' => 'Motorbike']))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['uuid', 'lender', 'lender_type', 'amount', 'repaid', 'remaining', 'percent_repaid', 'settled', 'overdue', 'borrowed_on', 'due_on', 'note', 'created_at', 'updated_at']]);

        $this->assertSame('200.00', $response->json('data.amount'));
        $this->assertSame('0.00', $response->json('data.repaid'));
        $this->assertSame('200.00', $response->json('data.remaining'));
        $this->assertFalse($response->json('data.settled'));
        $this->assertSame('2026-12-01', $response->json('data.due_on'));

        $this->assertDatabaseHas('borrowings', ['user_id' => $user->id, 'lender' => 'Mom', 'lender_type' => 'family']);
    }

    public function test_a_riel_amount_is_stored_in_dollars(): void
    {
        AppSetting::current()->update(['khr_per_usd' => 4000]);

        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BorrowingsWrite->value]);

        $this->postJson('/api/v1/borrowings', $this->payload(['amount' => '20000', 'currency' => 'KHR']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '5.00');

        $this->assertSame('5.0000', Borrowing::sole()->amount);
    }

    public function test_store_rejects_a_future_date_a_bad_type_and_a_due_date_before_it(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BorrowingsWrite->value]);

        $this->postJson('/api/v1/borrowings', $this->payload(['borrowed_on' => '2026-09-15']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('borrowed_on');

        $this->postJson('/api/v1/borrowings', $this->payload(['lender_type' => 'stranger']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lender_type');

        $this->postJson('/api/v1/borrowings', $this->payload(['due_on' => '2026-08-31']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_on');

        $this->assertDatabaseCount('borrowings', 0);
    }

    public function test_show_includes_the_ledger_newest_first(): void
    {
        $user = User::factory()->create();
        $borrowing = Borrowing::factory()->for($user)->create(['amount' => 100]);
        BorrowingRepayment::factory()->for($borrowing)->create(['amount' => 10, 'paid_on' => '2026-09-02']);
        BorrowingRepayment::factory()->for($borrowing)->create(['amount' => 20, 'paid_on' => '2026-09-05']);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $response = $this->getJson("/api/v1/borrowings/{$borrowing->uuid}")->assertOk();

        $this->assertSame('30.00', $response->json('data.repaid'));
        $this->assertSame('70.00', $response->json('data.remaining'));
        $this->assertSame(30, $response->json('data.percent_repaid'));
        $this->assertSame(['20.00', '10.00'], $response->json('data.repayments.*.amount'));
    }

    public function test_a_repayment_is_capped_at_what_is_still_owed(): void
    {
        $user = User::factory()->create();
        $borrowing = Borrowing::factory()->for($user)->create(['amount' => 100, 'borrowed_on' => '2026-09-01']);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsWrite->value]);

        $this->postJson("/api/v1/borrowings/{$borrowing->uuid}/repayments", ['amount' => '60', 'paid_on' => '2026-09-10', 'note' => 'First half'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '60.00')
            ->assertJsonPath('data.note', 'First half');

        $this->postJson("/api/v1/borrowings/{$borrowing->uuid}/repayments", ['amount' => '40.01', 'paid_on' => '2026-09-11'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        // Not before it was borrowed, not in the future.
        $this->postJson("/api/v1/borrowings/{$borrowing->uuid}/repayments", ['amount' => '10', 'paid_on' => '2026-08-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paid_on');
        $this->postJson("/api/v1/borrowings/{$borrowing->uuid}/repayments", ['amount' => '10', 'paid_on' => '2026-09-15'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paid_on');

        $this->postJson("/api/v1/borrowings/{$borrowing->uuid}/repayments", ['amount' => '40', 'paid_on' => '2026-09-11'])
            ->assertCreated();

        $this->assertSame(2, BorrowingRepayment::where('borrowing_id', $borrowing->id)->count());
        $this->assertTrue($borrowing->fresh()->isSettled());
    }

    public function test_update_cannot_take_the_amount_below_what_is_repaid(): void
    {
        $user = User::factory()->create();
        $borrowing = Borrowing::factory()->for($user)->create(['amount' => 100, 'borrowed_on' => '2026-09-01']);
        BorrowingRepayment::factory()->for($borrowing)->create(['amount' => 60]);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsWrite->value]);

        $this->patchJson("/api/v1/borrowings/{$borrowing->uuid}", $this->payload(['amount' => '50']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->patchJson("/api/v1/borrowings/{$borrowing->uuid}", $this->payload(['amount' => '60', 'lender' => 'Dad']))
            ->assertOk()
            ->assertJsonPath('data.lender', 'Dad')
            ->assertJsonPath('data.remaining', '0.00')
            ->assertJsonPath('data.settled', true)
            // An omitted due_on and note clear the old ones — PATCH takes the full shape.
            ->assertJsonPath('data.due_on', null);
    }

    public function test_deleting_a_repayment_is_scoped_to_its_borrowing(): void
    {
        $user = User::factory()->create();
        $borrowing = Borrowing::factory()->for($user)->create(['amount' => 100]);
        $repayment = BorrowingRepayment::factory()->for($borrowing)->create(['amount' => 100]);
        $other = Borrowing::factory()->for($user)->create();

        Sanctum::actingAs($user, [TokenAbility::BorrowingsWrite->value]);

        $this->deleteJson("/api/v1/borrowings/{$other->uuid}/repayments/{$repayment->uuid}")->assertNotFound();
        $this->deleteJson("/api/v1/borrowings/{$borrowing->uuid}/repayments/{$repayment->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('borrowing_repayments', ['id' => $repayment->id]);
        $this->assertFalse($borrowing->fresh()->isSettled());
    }

    public function test_someone_elses_borrowing_is_forbidden_unless_manage_all_is_held(): void
    {
        $user = User::factory()->create();
        $theirs = Borrowing::factory()->create(['borrowed_on' => '2026-09-01']);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value, TokenAbility::BorrowingsWrite->value]);

        $this->getJson("/api/v1/borrowings/{$theirs->uuid}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->patchJson("/api/v1/borrowings/{$theirs->uuid}", $this->payload())->assertForbidden();
        $this->postJson("/api/v1/borrowings/{$theirs->uuid}/repayments", ['amount' => '1', 'paid_on' => '2026-09-10'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/borrowings/{$theirs->uuid}")->assertForbidden();

        // The permission, not the role: granting it to a plain user must work.
        $user->givePermissionTo(Permission::BorrowingsManageAll->value);

        $this->getJson("/api/v1/borrowings/{$theirs->uuid}")->assertOk();

        // A repayment an admin records still belongs to whoever owes the money.
        $this->postJson("/api/v1/borrowings/{$theirs->uuid}/repayments", ['amount' => '1', 'paid_on' => '2026-09-10'])
            ->assertCreated();
        $this->assertDatabaseHas('borrowing_repayments', ['borrowing_id' => $theirs->id, 'user_id' => $theirs->user_id]);

        $this->deleteJson("/api/v1/borrowings/{$theirs->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('borrowings', ['id' => $theirs->id]);
        $this->assertDatabaseCount('borrowing_repayments', 0);
    }

    public function test_a_revoked_permission_bites_through_a_token_that_still_carries_the_ability(): void
    {
        $user = User::factory()->create();
        $user->revokePermissionTo(Permission::BorrowingsView->value);

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $this->getJson('/api/v1/borrowings')->assertForbidden();
    }

    public function test_an_unknown_or_non_uuid_key_is_a_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::BorrowingsRead->value]);

        $this->getJson('/api/v1/borrowings/1')->assertNotFound();
        $this->getJson('/api/v1/borrowings/0198f1a2-b3c4-7d5e-8f9a-0b1c2d3e4f5a')->assertNotFound();
    }
}
