<?php

namespace Database\Factories;

use App\Models\SavingsEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsEntry>
 */
class SavingsEntryFactory extends Factory
{
    /**
     * A deposit by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 5, 200),
            'saved_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'note' => null,
        ];
    }

    /** A withdrawal: stored negative, like the API writes it. */
    public function withdrawal(float $amount): static
    {
        return $this->state(fn () => ['amount' => -abs($amount)]);
    }
}
