<?php

namespace Database\Factories;

use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowingRepayment>
 */
class BorrowingRepaymentFactory extends Factory
{
    /**
     * user_id follows the borrowing's owner, so a factory repayment can never
     * belong to someone else's debt.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'borrowing_id' => Borrowing::factory(),
            'user_id' => fn (array $attributes) => Borrowing::find($attributes['borrowing_id'])->user_id,
            'amount' => fake()->randomFloat(2, 5, 100),
            'paid_on' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'note' => null,
        ];
    }
}
