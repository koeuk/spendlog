<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringKind;
use App\Models\Category;
use App\Models\RecurringRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringRule>
 */
class RecurringRuleFactory extends Factory
{
    /**
     * An expense rule starting today, with nothing written yet — the cursor
     * sits on starts_on exactly as a freshly created rule's does.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->toDateString();

        return [
            'user_id' => User::factory(),
            'kind' => RecurringKind::Expense,
            'category_id' => Category::factory(),
            'title' => fake()->randomElement(['Rent', 'Netflix', 'Gym', 'Internet']),
            'amount' => fake()->randomFloat(2, 5, 500),
            'frequency' => RecurringFrequency::Monthly,
            'starts_on' => $startsOn,
            'ends_on' => null,
            'next_run_on' => $startsOn,
            'last_run_on' => null,
            'active' => true,
            'note' => null,
        ];
    }

    /** An income rule: no category, a source-like title. */
    public function income(): static
    {
        return $this->state(fn () => [
            'kind' => RecurringKind::Income,
            'category_id' => null,
            'title' => fake()->randomElement(['Salary', 'Rent received', 'Dividend']),
        ]);
    }
}
