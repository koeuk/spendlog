<?php

namespace Database\Factories;

use App\Models\SavingsEntry;
use App\Models\SavingsGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsEntry>
 */
class SavingsEntryFactory extends Factory
{
    /**
     * A deposit by default. user_id follows the goal's owner, so a factory
     * entry can never belong to a goal someone else holds.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'savings_goal_id' => SavingsGoal::factory(),
            'user_id' => fn (array $attributes) => SavingsGoal::find($attributes['savings_goal_id'])->user_id,
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
