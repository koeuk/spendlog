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
        // `name` is translatable (spatie), so a bare string is stored under the
        // current locale. Written as an explicit map instead, so a factory row
        // matches what the request layer actually persists.
        return [
            'name' => ['en' => fake()->unique()->words(2, true)],
            'color' => fake()->randomElement(CategoryColor::cases()),
            'icon' => fake()->randomElement(CategoryIcon::cases()),
        ];
    }
}
