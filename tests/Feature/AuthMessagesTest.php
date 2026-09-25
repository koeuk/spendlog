<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The auth messages come from the framework's own lang/en/auth.php, not from
 * lang/en.json.
 *
 * Both catalogues answer __(), and JSON wins. So adding "auth.failed" to
 * en.json — which a sweep for untranslated strings will do, since the code
 * really does call __('auth.failed') — shadows the sentence with its own key,
 * and a failed sign-in tells the user "auth.failed". These tests assert the
 * sentence rather than the key, so the shadowing cannot come back unnoticed.
 */
class AuthMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** Keys that must resolve to a sentence, never to themselves. */
    public static function fileBasedKeys(): array
    {
        return [
            'auth.failed' => ['auth.failed'],
            'auth.password' => ['auth.password'],
            'auth.throttle' => ['auth.throttle'],
            'passwords.sent' => ['passwords.sent'],
            'validation.required' => ['validation.required'],
        ];
    }

    #[DataProvider('fileBasedKeys')]
    public function test_the_key_resolves_to_a_sentence(string $key): void
    {
        $this->assertNotSame($key, __($key), "__('{$key}') rendered its own key.");
    }

    public function test_no_json_entry_shadows_a_file_based_key(): void
    {
        $json = json_decode(file_get_contents(lang_path('en.json')), true);

        $shadowing = array_filter(
            array_keys($json),
            fn (string $key) => (bool) preg_match('/^(auth|passwords|validation|pagination)\.[a-z0-9_.]+$/', $key),
        );

        $this->assertSame([], array_values($shadowing));
    }

    public function test_a_failed_web_login_explains_itself(): void
    {
        User::factory()->create(['email' => 'someone@example.com']);

        $this->post('/login', ['email' => 'someone@example.com', 'password' => 'not-the-password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_a_failed_api_login_explains_itself(): void
    {
        User::factory()->create(['email' => 'someone@example.com']);

        $this->postJson('/api/v1/login', [
            'email' => 'someone@example.com',
            'password' => 'not-the-password',
            'device_name' => 'phone',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }
}
