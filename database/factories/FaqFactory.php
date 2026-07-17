<?php

namespace Database\Factories;

use App\Enums\FaqStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Faq>
 */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question' => ['en' => rtrim($this->faker->sentence(), '.').'?'],
            'answer' => ['en' => $this->faker->paragraph()],
            'status' => FaqStatus::Published->value,
            'position' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => FaqStatus::Draft->value]);
    }
}
