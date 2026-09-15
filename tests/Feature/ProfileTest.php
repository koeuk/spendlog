<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString('/storage/avatars/', $user->avatar_url);
    }

    public function test_a_new_photo_replaces_the_old_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $first = $user->refresh()->avatar_path;

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('second.png'),
        ]);
        $second = $user->refresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_user_can_remove_their_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);
        $path = $user->refresh()->avatar_path;

        $response = $this->actingAs($user)->delete(route('profile.avatar.destroy'));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->refresh()->avatar_path);
        $this->assertNull($user->avatar_url);
    }

    public function test_the_photo_must_be_an_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ]);

        $response
            ->assertSessionHasErrors('avatar')
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_the_photo_needs_no_profile_permission(): void
    {
        Storage::fake('public');

        // Locked out of editing name and email — the photo is still theirs to
        // set, as it is over the API.
        $user = User::factory()->create();
        $user->revokePermissionTo(Permission::ProfileUpdate->value);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Renamed', 'email' => $user->email])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('me.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->avatar_path);
    }

    public function test_guests_cannot_upload_a_profile_photo(): void
    {
        $this->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])->assertRedirect(route('login'));
    }
}
