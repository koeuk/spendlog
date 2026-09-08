<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source' => fake()->randomElement(['Salary', 'Freelance', 'Gift', 'Refund']),
            'amount' => fake()->randomFloat(2, 10, 3000),
            // Never in the future — IncomeRequest rejects that, so a factory
            // that produced it would build rows the API itself would refuse.
            'received_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'note' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
