<?php

namespace Tests\Feature\Settings;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer's editable pages. The migration seeds a fixed set (about, privacy);
 * the admin edits them, everyone reads the published ones.
 */
class PageTest extends TestCase
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

    private function about(): Page
    {
        return Page::where('slug', 'about')->sole();
    }

    // --- The migration seeds the fixed set ---------------------------------

    public function test_the_migration_seeds_about_and_privacy_as_empty_drafts(): void
    {
        $this->assertSame(['about', 'privacy'], Page::orderBy('slug')->pluck('slug')->all());
        $this->assertFalse($this->about()->published);
    }

    public function test_the_page_seeder_fills_starter_copy_as_drafts(): void
    {
        $this->seed(PageSeeder::class);

        $about = $this->about()->fresh();
        $this->assertSame('About', $about->getTranslation('title', 'en'));
        $this->assertSame('អំពី', $about->getTranslation('title', 'km'));
        // Starter copy is never auto-published — an admin reads it first.
        $this->assertFalse($about->published);
    }

    public function test_the_page_seeder_never_overwrites_edited_copy(): void
    {
        $this->about()->update(['title' => ['en' => 'My own about'], 'published' => true]);

        // Runs on every deploy, so it must leave an admin's words alone.
        $this->seed(PageSeeder::class);

        $about = $this->about()->fresh();
        $this->assertSame('My own about', $about->getTranslation('title', 'en'));
        $this->assertTrue($about->published);
    }

    // --- Permission gating -------------------------------------------------

    public function test_an_admin_can_open_the_pages_editor(): void
    {
        $this->actingAs($this->admin())->get(route('pages.index'))->assertOk();
    }

    public function test_a_normal_user_cannot_open_the_pages_editor(): void
    {
        $this->actingAs($this->user())->get(route('pages.index'))->assertForbidden();
    }

    public function test_a_normal_user_cannot_update_a_page(): void
    {
        $this->actingAs($this->user())
            ->patch(route('pages.update', $this->about()), [
                'title' => ['en' => 'Hacked'],
                'body' => ['en' => 'x'],
                'published' => true,
            ])
            ->assertForbidden();

        $this->assertSame('', (string) $this->about()->fresh()->title);
    }

    public function test_the_gate_follows_the_permission_not_the_role(): void
    {
        $user = $this->user();
        $user->givePermissionTo(Permission::SettingsPages->value);

        $this->actingAs($user->fresh())->get(route('pages.index'))->assertOk();
    }

    // --- Editing -----------------------------------------------------------

    public function test_an_admin_saves_a_translatable_page(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('pages.update', $this->about()), [
                'title' => ['en' => 'About us', 'km' => 'អំពីយើង'],
                'body' => ['en' => 'We track spending.', 'km' => 'យើងតាមដានចំណាយ។'],
                'published' => true,
            ])
            ->assertRedirect();

        $about = $this->about()->fresh();
        $this->assertSame('About us', $about->getTranslation('title', 'en'));
        $this->assertSame('អំពីយើង', $about->getTranslation('title', 'km'));
        $this->assertTrue($about->published);
    }

    public function test_a_published_page_requires_the_fallback_title(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('pages.update', $this->about()), [
                'title' => ['en' => '', 'km' => 'x'],
                'body' => ['en' => '', 'km' => 'x'],
                'published' => true,
            ])
            ->assertSessionHasErrors(['title.en', 'body.en']);
    }

    public function test_a_draft_may_be_saved_half_written(): void
    {
        // Off, so the fallback is not required — an admin can park a draft.
        $this->actingAs($this->admin())
            ->patch(route('pages.update', $this->about()), [
                'title' => ['en' => ''],
                'body' => ['en' => ''],
                'published' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    // --- Public page -------------------------------------------------------

    public function test_a_published_page_is_readable(): void
    {
        $about = $this->about();
        $about->update(['title' => ['en' => 'About'], 'body' => ['en' => 'Hi'], 'published' => true]);

        $response = $this->actingAs($this->user())->get(route('pages.show', $about));

        $response->assertOk();
        $this->assertSame('About', $response->viewData('page')['props']['page']['title']);
    }

    public function test_a_draft_page_shows_a_placeholder_not_its_draft_body(): void
    {
        // The footer links to About/Policy even before they are published, so a
        // draft must not 404 — but it must not leak its half-written body either.
        $this->about()->update([
            'title' => ['en' => 'Secret draft title'],
            'body' => ['en' => 'Half-written internal draft.'],
            'published' => false,
        ]);

        $response = $this->actingAs($this->user())->get(route('pages.show', $this->about()));

        $response->assertOk();

        $page = $response->viewData('page')['props']['page'];
        // The fixed label, never the draft title or body.
        $this->assertSame('About', $page['title']);
        $this->assertStringNotContainsString('Secret draft title', $page['title']);
        $this->assertStringNotContainsString('Half-written internal draft.', $page['body']);
    }

    public function test_the_public_page_resolves_to_the_readers_locale(): void
    {
        $about = $this->about();
        $about->update([
            'title' => ['en' => 'About', 'km' => 'អំពី'],
            'body' => ['en' => 'Hi', 'km' => 'សួស្តី'],
            'published' => true,
        ]);

        $response = $this->actingAs($this->user())
            ->withSession(['locale' => 'km'])
            ->get(route('pages.show', $about));

        $this->assertSame('អំពី', $response->viewData('page')['props']['page']['title']);
    }

    // --- Shared footer list ------------------------------------------------

    public function test_the_footer_list_carries_only_published_pages_with_a_title(): void
    {
        $this->about()->update(['title' => ['en' => 'About'], 'body' => ['en' => 'x'], 'published' => true]);
        // privacy stays a draft with no title

        $footer = $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->viewData('page')['props']['footer_pages'];

        $this->assertCount(1, $footer);
        $this->assertSame('about', $footer[0]['slug']);
        $this->assertSame('About', $footer[0]['title']);
    }
}
