<?php

namespace Database\Factories;

use App\Enums\CategoryColor;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Emergency fund', 'New laptop', 'Holiday', 'Motorbike']),
            'target_amount' => fake()->randomFloat(2, 100, 5000),
            'deadline' => fake()->boolean(50) ? fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d') : null,
            'color' => fake()->randomElement(CategoryColor::cases()),
        ];
    }
}
