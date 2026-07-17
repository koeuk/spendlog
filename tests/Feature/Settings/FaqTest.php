<?php

namespace Tests\Feature\Settings;

use App\Enums\FaqStatus;
use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->applyRole(RoleName::Admin);

        return $admin;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->applyRole(RoleName::User);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'question' => ['en' => 'How do I add an expense?', 'km' => 'តើបញ្ចូលចំណាយយ៉ាងណា?'],
            'answer' => ['en' => 'Tap Add expense.', 'km' => 'ចុច បញ្ចូលចំណាយ។'],
            'status' => FaqStatus::Published->value,
        ], $overrides);
    }

    // --- Permission gating ------------------------------------------------

    public function test_an_admin_can_open_the_faq_manager(): void
    {
        $this->actingAs($this->admin())->get(route('faqs.index'))->assertOk();
    }

    public function test_a_normal_user_cannot_open_the_faq_manager(): void
    {
        $this->actingAs($this->user())->get(route('faqs.index'))->assertForbidden();
    }

    public function test_a_normal_user_cannot_create_an_entry(): void
    {
        $this->actingAs($this->user())
            ->post(route('faqs.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Faq::count());
    }

    public function test_the_gate_follows_the_permission_not_the_role(): void
    {
        // An ordinary user granted just the one permission gets in.
        $user = $this->user();
        $user->givePermissionTo(Permission::SettingsFaq->value);

        $this->actingAs($user->fresh())->get(route('faqs.index'))->assertOk();
    }

    // --- CRUD -------------------------------------------------------------

    public function test_an_admin_creates_a_translatable_entry(): void
    {
        $this->actingAs($this->admin())
            ->post(route('faqs.store'), $this->payload())
            ->assertRedirect();

        $faq = Faq::sole();
        $this->assertSame('How do I add an expense?', $faq->getTranslation('question', 'en'));
        $this->assertSame('តើបញ្ចូលចំណាយយ៉ាងណា?', $faq->getTranslation('question', 'km'));
        $this->assertSame(FaqStatus::Published, $faq->status);
    }

    public function test_english_question_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('faqs.store'), $this->payload(['question' => ['en' => '', 'km' => 'x']]))
            ->assertSessionHasErrors('question.en');

        $this->assertSame(0, Faq::count());
    }

    public function test_blanking_a_locale_clears_it_rather_than_storing_empty(): void
    {
        $faq = Faq::factory()->create([
            'question' => ['en' => 'Q', 'km' => 'ខ្មែរ'],
            'answer' => ['en' => 'A'],
        ]);

        $this->actingAs($this->admin())
            ->patch(route('faqs.update', $faq), $this->payload([
                'question' => ['en' => 'Q', 'km' => ''],
                'answer' => ['en' => 'A'],
            ]))
            ->assertRedirect();

        // The km key is gone, not stored as '' — so a km reader falls back to en.
        $this->assertArrayNotHasKey('km', $faq->fresh()->getTranslations('question'));
    }

    public function test_an_admin_deletes_an_entry(): void
    {
        $faq = Faq::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('faqs.destroy', $faq))
            ->assertRedirect();

        $this->assertSame(0, Faq::count());
    }

    // --- Reorder ----------------------------------------------------------

    public function test_reorder_rewrites_positions_from_the_given_order(): void
    {
        $a = Faq::factory()->create(['position' => 0]);
        $b = Faq::factory()->create(['position' => 1]);
        $c = Faq::factory()->create(['position' => 2]);

        $this->actingAs($this->admin())
            ->post(route('faqs.reorder'), ['uuids' => [$c->uuid, $a->uuid, $b->uuid]])
            ->assertRedirect();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_a_normal_user_cannot_reorder(): void
    {
        $faq = Faq::factory()->create();

        $this->actingAs($this->user())
            ->post(route('faqs.reorder'), ['uuids' => [$faq->uuid]])
            ->assertForbidden();
    }

    // --- Public help page -------------------------------------------------

    public function test_the_help_page_shows_published_entries_in_order(): void
    {
        Faq::factory()->create(['question' => ['en' => 'Second'], 'position' => 1]);
        Faq::factory()->create(['question' => ['en' => 'First'], 'position' => 0]);

        $response = $this->actingAs($this->user())->get(route('help'));

        $response->assertOk();
        $faqs = $response->viewData('page')['props']['faqs'];

        $this->assertCount(2, $faqs);
        $this->assertSame('First', $faqs[0]['question']);
        $this->assertSame('Second', $faqs[1]['question']);
    }

    public function test_the_help_page_hides_drafts(): void
    {
        Faq::factory()->create(['question' => ['en' => 'Live']]);
        Faq::factory()->draft()->create(['question' => ['en' => 'Hidden']]);

        $response = $this->actingAs($this->user())->get(route('help'));

        $faqs = $response->viewData('page')['props']['faqs'];
        $this->assertCount(1, $faqs);
        $this->assertSame('Live', $faqs[0]['question']);
    }

    public function test_the_help_page_resolves_to_the_readers_locale(): void
    {
        Faq::factory()->create([
            'question' => ['en' => 'How?', 'km' => 'យ៉ាងណា?'],
            'answer' => ['en' => 'Like this.', 'km' => 'ដូចនេះ។'],
        ]);

        // SetLocale reads the chosen locale from the session on each request.
        $response = $this->actingAs($this->user())
            ->withSession(['locale' => 'km'])
            ->get(route('help'));

        $faqs = $response->viewData('page')['props']['faqs'];

        $this->assertSame('យ៉ាងណា?', $faqs[0]['question']);
        $this->assertSame('ដូចនេះ។', $faqs[0]['answer']);
    }

    public function test_the_help_page_is_open_to_any_signed_in_user(): void
    {
        $this->actingAs($this->user())->get(route('help'))->assertOk();
    }
}
