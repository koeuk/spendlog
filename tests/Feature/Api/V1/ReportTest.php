<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_a_report_carries_series_breakdown_and_stats(): void
    {
        $user = $this->user();
        $category = Category::factory()->create();

        Expense::factory()->for($user)->for($category)->create([
            'price' => '12.50',
            'spent_on' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user, [TokenAbility::ReportsRead->value]);

        $this->getJson('/api/v1/reports?period=month')
            ->assertOk()
            ->assertJsonPath('data.granularity', 'month')
            // Money is a string at the API boundary, even though the shared
            // services speak floats to the Vue pages.
            ->assertJsonPath('data.stats.total', '12.50')
            ->assertJsonPath('data.breakdown.0.total', '12.50')
            ->assertJsonStructure([
                'data' => [
                    'granularity', 'anchor', 'options',
                    'series' => ['label', 'total', 'buckets'],
                    'breakdown', 'stats',
                ],
            ]);
    }

    public function test_a_junk_period_falls_back_to_month(): void
    {
        Sanctum::actingAs($this->user(), [TokenAbility::ReportsRead->value]);

        $this->getJson('/api/v1/reports?period=fortnight')
            ->assertOk()
            ->assertJsonPath('data.granularity', 'month');
    }

    public function test_a_token_without_the_ability_is_refused(): void
    {
        Sanctum::actingAs($this->user(), [TokenAbility::DashboardRead->value]);

        $this->getJson('/api/v1/reports')->assertForbidden();
    }

    public function test_the_report_downloads_as_a_file_over_the_api(): void
    {
        Sanctum::actingAs($this->user(), [TokenAbility::ReportsRead->value]);

        $this->get('/api/v1/reports/export/csv?period=month')
            ->assertOk()
            ->assertDownload();
    }

    public function test_reports_read_is_in_a_regular_users_default_token(): void
    {
        $this->assertContains(
            TokenAbility::ReportsRead->value,
            TokenAbility::defaults($this->user()),
        );
    }
}
