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
            [
                'name' => ['en' => 'Food', 'km' => 'អាហារ'],
                'color' => CategoryColor::Orange,
                'icon' => CategoryIcon::Utensils,
            ],
            [
                'name' => ['en' => 'Transport', 'km' => 'ការធ្វើដំណើរ'],
                'color' => CategoryColor::Blue,
                'icon' => CategoryIcon::Car,
            ],
            [
                'name' => ['en' => 'Bills', 'km' => 'វិក្កយបត្រ'],
                'color' => CategoryColor::Red,
                'icon' => CategoryIcon::Receipt,
            ],
            [
                'name' => ['en' => 'Shopping', 'km' => 'ការទិញទំនិញ'],
                'color' => CategoryColor::Purple,
                'icon' => CategoryIcon::ShoppingBag,
            ],
            [
                'name' => ['en' => 'Other', 'km' => 'ផ្សេងៗ'],
                'color' => CategoryColor::Slate,
                'icon' => CategoryIcon::CircleDashed,
            ],
        ];

        foreach ($categories as $category) {
            // Matched on the English name: it is the stable identifier across
            // re-seeds, and a JSON column cannot be matched on as a whole.
            $existing = Category::query()
                ->whereJsonContains('name->en', $category['name']['en'])
                ->first() ?? new Category;

            $existing->setTranslations('name', $category['name']);
            $existing->color = $category['color'];
            $existing->icon = $category['icon'];
            $existing->save();
        }
    }
}
