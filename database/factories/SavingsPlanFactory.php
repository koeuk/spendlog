<?php

namespace Database\Factories;

use App\Models\SavingsPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsPlan>
 */
class SavingsPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 50, 2000),
            // Always the first of the month: the (user, month) unique index
            // only collides when the day matches.
            'month' => CarbonImmutable::now()->startOfMonth()->toDateString(),
        ];
    }

    public function forMonth(string $month): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => CarbonImmutable::parse($month.'-01')->startOfMonth()->toDateString(),
        ]);
    }
}
