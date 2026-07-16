<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CategoryIndexTest extends TestCase
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

    /**
     * The raw Inertia payload, straight off the data-page attribute.
     *
     * Read as a string rather than through assertInertia(): both json() and
     * AssertableInertia decode with assoc: true, which turns {} and [] into the
     * same PHP array — and that difference is the whole point of the first test
     * below. Sending an X-Inertia header by hand instead would 409 on the asset
     * version.
     */
    private function payload(TestResponse $response): string
    {
        preg_match('/data-page="([^"]*)"/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches, 'No Inertia data-page attribute in the response.');

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    /**
     * request()->only() returns [] when nothing is set, and [] serialises to a
     * JSON array. `filters.filter` in JS then resolves to Array.prototype.filter
     * — a real function, so optional chaining does not stop — and reading .name
     * off it yields the string "filter", which rendered in the search box.
     */
    public function test_filters_are_an_object_even_when_no_filter_is_applied(): void
    {
        $response = $this->actingAs($this->user())->get(route('categories.index'));

        $response->assertOk();
        $payload = $this->payload($response);

        $this->assertStringContainsString('"filters":{}', $payload);
        $this->assertStringNotContainsString('"filters":[]', $payload);
    }

    public function test_filters_carry_the_applied_search_back_to_the_page(): void
    {
        $response = $this->actingAs($this->user())
            ->get(route('categories.index', ['filter' => ['name' => 'Pets']]));

        $response->assertOk();

        $this->assertStringContainsString(
            '"filters":{"filter":{"name":"Pets"}}',
            $this->payload($response),
        );
    }

    public function test_searching_by_name_narrows_the_list(): void
    {
        Category::factory()->create(['name' => ['en' => 'Food', 'km' => 'អាហារ']]);
        Category::factory()->create(['name' => ['en' => 'Transport', 'km' => 'ដឹកជញ្ជូន']]);

        $response = $this->actingAs($this->user())
            ->get(route('categories.index', ['filter' => ['name' => 'Foo']]));

        $response->assertOk();
        $props = json_decode($this->payload($response), true)['props'];

        $this->assertCount(1, $props['categories']);
        $this->assertSame('Food', $props['categories'][0]['name']['en']);
    }

    /** @return list<string> The English names, in the order the page lists them. */
    private function names(TestResponse $response): array
    {
        $props = json_decode($this->payload($response), true)['props'];

        return array_column(array_column($props['categories'], 'name'), 'en');
    }

    public function test_the_list_is_newest_first_by_default(): void
    {
        Category::factory()->create(['name' => ['en' => 'Oldest'], 'created_at' => '2026-01-01 09:00:00']);
        Category::factory()->create(['name' => ['en' => 'Newest'], 'created_at' => '2026-03-01 09:00:00']);
        Category::factory()->create(['name' => ['en' => 'Middle'], 'created_at' => '2026-02-01 09:00:00']);

        $response = $this->actingAs($this->user())->get(route('categories.index'));

        $response->assertOk();
        $this->assertSame(['Newest', 'Middle', 'Oldest'], $this->names($response));
    }

    public function test_sort_created_lists_oldest_first(): void
    {
        Category::factory()->create(['name' => ['en' => 'Oldest'], 'created_at' => '2026-01-01 09:00:00']);
        Category::factory()->create(['name' => ['en' => 'Newest'], 'created_at' => '2026-03-01 09:00:00']);
        Category::factory()->create(['name' => ['en' => 'Middle'], 'created_at' => '2026-02-01 09:00:00']);

        $response = $this->actingAs($this->user())
            ->get(route('categories.index', ['sort' => 'created']));

        $response->assertOk();
        $this->assertSame(['Oldest', 'Middle', 'Newest'], $this->names($response));
    }

    /**
     * A seed or import stamps every row with the same created_at, and MySQL may
     * then return those tied rows in a different order on each query. Without a
     * tie-break the list reshuffles on a plain reload, so id decides — in the
     * direction that was asked for, which also makes oldest the exact mirror of
     * newest rather than an unrelated shuffle of the same rows.
     */
    public function test_rows_sharing_a_timestamp_still_have_a_stable_mirrored_order(): void
    {
        $tied = '2026-01-01 09:00:00';

        foreach (['A', 'B', 'C'] as $name) {
            Category::factory()->create(['name' => ['en' => $name], 'created_at' => $tied]);
        }

        $newest = $this->actingAs($this->user())->get(route('categories.index'));
        $oldest = $this->actingAs($this->user())
            ->get(route('categories.index', ['sort' => 'created']));

        $this->assertSame(['C', 'B', 'A'], $this->names($newest));
        $this->assertSame(['A', 'B', 'C'], $this->names($oldest));
    }

    public function test_sorting_and_searching_apply_together(): void
    {
        Category::factory()->create(['name' => ['en' => 'Food'], 'created_at' => '2026-01-01 09:00:00']);
        Category::factory()->create(['name' => ['en' => 'Fuel'], 'created_at' => '2026-03-01 09:00:00']);
        Category::factory()->create(['name' => ['en' => 'Transport'], 'created_at' => '2026-02-01 09:00:00']);

        $response = $this->actingAs($this->user())->get(route('categories.index', [
            'filter' => ['name' => 'Fu'],
            'sort' => 'created',
        ]));

        $response->assertOk();
        $this->assertSame(['Fuel'], $this->names($response));
    }

    /** 'en' is a key in every row's JSON, and part of no category's name. */
    public function test_searching_the_locale_key_matches_nothing(): void
    {
        Category::factory()->create(['name' => ['en' => 'Food', 'km' => 'អាហារ']]);
        Category::factory()->create(['name' => ['en' => 'Transport', 'km' => 'ដឹកជញ្ជូន']]);

        $response = $this->actingAs($this->user())
            ->get(route('categories.index', ['filter' => ['name' => 'en']]));

        $response->assertOk();
        $props = json_decode($this->payload($response), true)['props'];

        $this->assertCount(0, $props['categories']);
    }
}
