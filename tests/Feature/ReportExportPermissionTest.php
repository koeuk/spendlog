<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The export is the report, as a file. Whoever may not read the report on
 * screen may not download it either — the page and the file have to answer
 * the same question about permission, or revoking reports.view only hides
 * the screen while the data stays one URL away.
 */
class ReportExportPermissionTest extends TestCase
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

    public function test_the_report_page_is_refused_without_the_permission(): void
    {
        $user = $this->user();
        $user->revokePermissionTo(Permission::ReportsView->value);

        $this->actingAs($user)->get('/reports')->assertForbidden();
    }

    #[DataProvider('formats')]
    public function test_the_export_is_refused_without_the_permission(string $format): void
    {
        $user = $this->user();
        $user->revokePermissionTo(Permission::ReportsView->value);

        $this->actingAs($user)->get("/reports/export/{$format}")->assertForbidden();
    }

    #[DataProvider('formats')]
    public function test_the_export_still_works_with_the_permission(string $format): void
    {
        $this->actingAs($this->user())
            ->get("/reports/export/{$format}")
            ->assertOk()
            ->assertDownload();
    }

    public static function formats(): array
    {
        return [
            'pdf' => ['pdf'],
            'xlsx' => ['xlsx'],
            'csv' => ['csv'],
        ];
    }
}
