<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workout>
 */
class WorkoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Never in the future — WorkoutRequest rejects that, so a factory
            // that produced it would build rows the API itself would refuse.
            'performed_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'duration_seconds' => fake()->numberBetween(15 * 60, 120 * 60),
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    /** A session logged from memory: sets, but no stopwatch reading. */
    public function untimed(): static
    {
        return $this->state(fn () => ['duration_seconds' => null]);
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['performed_on' => $date]);
    }
}
