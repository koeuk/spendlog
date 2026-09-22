<?php

namespace Database\Factories;

use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeSource>
 */
class IncomeSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Unique per account, so a test building several does not trip the
            // constraint on a repeated draw from a short list.
            'name' => fake()->unique()->words(2, true),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
