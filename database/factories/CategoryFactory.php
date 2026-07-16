<?php

namespace Database\Factories;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Unique because categories.name carries a unique index.
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(CategoryColor::cases()),
            'icon' => fake()->randomElement(CategoryIcon::cases()),
        ];
    }
}
