<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            // Set explicitly even though the column defaults to active: a DB
            // default only lands on insert, so the in-memory model returned by
            // create() would carry a null status until refreshed — and
            // $user->status->canSignIn() then fatals on null.
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Give the account the ordinary user role and its permissions, matching what
     * registering through the app actually produces.
     *
     * Without this a factory user holds no permissions and every policy denies
     * it, so tests would 403 on things a real signed-up person can plainly do.
     * Needs the permission catalogue in place — Tests\TestCase seeds RoleSeeder
     * for exactly this reason.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => $user->applyRole(RoleName::User));
    }

    /** The starting set for an admin, as UserController::store would grant it. */
    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->applyRole(RoleName::Admin));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
