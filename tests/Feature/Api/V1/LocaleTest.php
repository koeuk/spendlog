<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Accept-Language on the API: the only way a token client can ask for Khmer,
 * since it has no session for the web's switcher to write into.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function food(): Category
    {
        return Category::factory()->create(['name' => ['en' => 'Food', 'km' => 'អាហារ']]);
    }

    private function signIn(): void
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        Sanctum::actingAs($user, [TokenAbility::CategoriesRead->value]);
    }

    public function test_english_is_the_default(): void
    {
        $category = $this->food();
        $this->signIn();

        $this->getJson("/api/v1/categories/{$category->uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Food');
    }

    public function test_accept_language_picks_khmer(): void
    {
        $category = $this->food();
        $this->signIn();

        $this->getJson("/api/v1/categories/{$category->uuid}", ['Accept-Language' => 'km'])
            ->assertOk()
            ->assertJsonPath('data.name', 'អាហារ');
    }

    public function test_a_region_suffix_and_a_list_are_tolerated(): void
    {
        $category = $this->food();
        $this->signIn();

        $this->getJson("/api/v1/categories/{$category->uuid}", ['Accept-Language' => 'km-KH,en;q=0.8'])
            ->assertOk()
            ->assertJsonPath('data.name', 'អាហារ');
    }

    public function test_an_unknown_language_leaves_the_default(): void
    {
        $category = $this->food();
        $this->signIn();

        $this->getJson("/api/v1/categories/{$category->uuid}", ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Food');
    }
}
