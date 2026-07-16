<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'amount' => fake()->randomFloat(2, 50, 2000),
            // Always the first of the month: the (user, category_key, month)
            // unique index only collides when the day matches.
            'month' => CarbonImmutable::now()->startOfMonth()->toDateString(),
        ];
    }

    /**
     * The overall budget covering every category, stored with a null category.
     */
    public function overall(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => null,
        ]);
    }

    public function forMonth(string $month): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => CarbonImmutable::parse($month.'-01')->startOfMonth()->toDateString(),
        ]);
    }
}
