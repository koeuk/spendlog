<?php

namespace Database\Seeders;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Food', 'color' => CategoryColor::Orange, 'icon' => CategoryIcon::Utensils],
            ['name' => 'Transport', 'color' => CategoryColor::Blue, 'icon' => CategoryIcon::Car],
            ['name' => 'Bills', 'color' => CategoryColor::Red, 'icon' => CategoryIcon::Receipt],
            ['name' => 'Shopping', 'color' => CategoryColor::Purple, 'icon' => CategoryIcon::ShoppingBag],
            ['name' => 'Other', 'color' => CategoryColor::Slate, 'icon' => CategoryIcon::CircleDashed],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                ['color' => $category['color'], 'icon' => $category['icon']],
            );
        }
    }
}
