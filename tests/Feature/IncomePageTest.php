<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Income page: own rows only, the same forms as the API behind it, and
 * the permission gates that decide who may open it at all.
 */
class IncomePageTest extends TestCase
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

    /** @return array<int, string> every source across the grouped days */
    private function sources(array $props): array
    {
        return collect($props['days'])
            ->flatMap(fn (array $day) => $day['incomes'])
            ->pluck('source')
            ->all();
    }

    public function test_the_page_requires_authentication(): void
    {
        $this->get(route('incomes.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_lists_only_the_viewers_own_income_newest_first(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        Income::factory()->for($me)->create(['source' => 'Older', 'received_on' => '2026-08-01']);
        Income::factory()->for($me)->create(['source' => 'Newer', 'received_on' => '2026-09-01']);
        Income::factory()->for($someoneElse)->create(['source' => 'Theirs', 'received_on' => '2026-09-05']);

        $response = $this->actingAs($me)->get(route('incomes.index'));

        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame(['Newer', 'Older'], $this->sources($props));
        $this->assertSame('Incomes/Index', json_decode(html_entity_decode(
            preg_match('/data-page="([^"]*)"/', $response->getContent(), $m) ? $m[1] : '{}',
            ENT_QUOTES,
        ), true)['component']);
    }

    public function test_the_page_filters_by_source_and_month(): void
    {
        $me = $this->ordinaryUser();

        Income::factory()->for($me)->create(['source' => 'Salary', 'received_on' => '2026-09-01']);
        Income::factory()->for($me)->create(['source' => 'Salary', 'received_on' => '2026-08-01']);
        Income::factory()->for($me)->create(['source' => 'Gift', 'received_on' => '2026-09-02']);

        $bySource = $this->props($this->actingAs($me)->get(route('incomes.index', ['filter' => ['source' => 'sal']])));
        $this->assertCount(2, $this->sources($bySource));

        $byMonth = $this->props($this->actingAs($me)->get(route('incomes.index', ['month' => '09', 'year' => '2026'])));
        $this->assertEqualsCanonicalizing(['Salary', 'Gift'], $this->sources($byMonth));
        $this->assertSame('09', $byMonth['month']);
        $this->assertSame('2026', $byMonth['year']);
    }

    public function test_the_form_offers_the_viewers_own_sources_most_used_first(): void
    {
        $me = $this->ordinaryUser();
        Income::factory()->for($me)->count(2)->create(['source' => 'Salary']);
        Income::factory()->for($me)->create(['source' => 'Gift']);
        Income::factory()->create(['source' => 'Theirs']);

        $props = $this->props($this->actingAs($me)->get(route('incomes.create'))->assertOk());

        $this->assertSame(['Salary', 'Gift'], $props['sources']);
    }

    public function test_an_account_without_the_permission_is_refused(): void
    {
        $me = $this->ordinaryUser();
        $me->revokePermissionTo(Permission::IncomesView->value);

        $this->actingAs($me->fresh())->get(route('incomes.index'))->assertForbidden();
    }

    public function test_storing_creates_the_row_for_the_signed_in_user_and_returns_to_the_month(): void
    {
        $me = $this->ordinaryUser();

        $response = $this->actingAs($me)->post(route('incomes.store'), [
            'source' => 'Salary',
            'amount' => '1200',
            'received_on' => '2026-09-01',
            'note' => 'September pay',
            'return_query' => ['month' => '09', 'year' => '2026'],
        ]);

        $response->assertRedirect(route('incomes.index', ['month' => '09', 'year' => '2026']));

        $this->assertDatabaseHas('incomes', [
            'user_id' => $me->id,
            'source' => 'Salary',
            'amount' => '1200.0000',
            'received_on' => '2026-09-01',
            'note' => 'September pay',
        ]);
    }

    public function test_a_riel_amount_is_stored_in_dollars(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)->post(route('incomes.store'), [
            'source' => 'Gift',
            'amount' => '41000',
            'currency' => 'KHR',
            'received_on' => '2026-09-01',
        ]);

        $this->assertSame(1, Income::query()->forUser($me->id)->count());
        $this->assertEqualsWithDelta(10.0, (float) Income::query()->forUser($me->id)->value('amount'), 0.01);
    }

    public function test_a_future_date_is_rejected(): void
    {
        $me = $this->ordinaryUser();

        $this->actingAs($me)
            ->from(route('incomes.create'))
            ->post(route('incomes.store'), [
                'source' => 'Salary',
                'amount' => '10',
                'received_on' => '2026-09-09',
            ])
            ->assertRedirect(route('incomes.create'))
            ->assertSessionHasErrors('received_on');

        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_updating_and_deleting_are_limited_to_the_owner(): void
    {
        $me = $this->ordinaryUser();
        $someoneElse = $this->ordinaryUser();

        $mine = Income::factory()->for($me)->create(['source' => 'Salary']);
        $theirs = Income::factory()->for($someoneElse)->create(['source' => 'Theirs']);

        $this->actingAs($me)->get(route('incomes.edit', $theirs))->assertForbidden();
        $this->actingAs($me)->put(route('incomes.update', $theirs), [
            'source' => 'Hijacked', 'amount' => '1', 'received_on' => '2026-09-01',
        ])->assertForbidden();
        $this->actingAs($me)->delete(route('incomes.destroy', $theirs))->assertForbidden();

        $this->actingAs($me)->put(route('incomes.update', $mine), [
            'source' => 'Bonus', 'amount' => '250', 'received_on' => '2026-09-02',
        ])->assertRedirect(route('incomes.index'));

        $this->assertSame('Bonus', $mine->fresh()->source);

        $this->actingAs($me)->delete(route('incomes.destroy', $mine))->assertRedirect();

        $this->assertDatabaseMissing('incomes', ['id' => $mine->id]);
        $this->assertDatabaseHas('incomes', ['id' => $theirs->id, 'source' => 'Theirs']);
    }
}
