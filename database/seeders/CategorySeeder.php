<?php

namespace Database\Seeders;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The default category set: Coffee, and nothing else.
     *
     * Deliberately one row. The set used to ship forty-five categories grouped
     * and coloured by theme, which meant every new install started with a list
     * nobody had chosen — and a category cannot be deleted once an expense,
     * budget or recurring rule points at it, so the unwanted ones stayed.
     * Starting with one and adding as you go is the cheaper direction: the
     * expense form creates a category inline from whatever name is typed.
     *
     * @return array<int, array{0: string, 1: string, 2: CategoryColor, 3: CategoryIcon}>
     */
    private function categories(): array
    {
        return [
            ['Coffee', 'កាហ្វេ', CategoryColor::Amber, CategoryIcon::Coffee],
        ];
    }

    public function run(): void
    {
        foreach ($this->categories() as [$en, $km, $color, $icon]) {
            /*
             * Matched on the English name: it is the stable identifier across
             * re-seeds, and a JSON column cannot be matched on as a whole.
             *
             * Upsert rather than insert, so running this twice does not duplicate
             * the set — and so an existing row (Food, Coffee…) gains its Khmer
             * name instead of being skipped.
             */
            $category = Category::query()
                ->whereJsonContains('name->en', $en)
                ->first() ?? new Category;

            $category->setTranslations('name', ['en' => $en, 'km' => $km]);
            $category->color = $color;
            $category->icon = $icon;
            $category->save();
        }
    }
}
