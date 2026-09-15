<?php

namespace Database\Factories;

use App\Enums\LenderType;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrowing>
 */
class BorrowingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lender' => fake()->randomElement(['Mom', 'Sokha', 'ABA Bank', 'Dara']),
            'lender_type' => fake()->randomElement(LenderType::cases()),
            'amount' => fake()->randomFloat(2, 20, 2000),
            // Never in the future — BorrowingRequest rejects that, so a
            // factory that produced it would build rows the API refuses.
            'borrowed_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'due_on' => null,
            'note' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }
}
