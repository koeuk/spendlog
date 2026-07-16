<?php

namespace Database\Seeders;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The default category set.
     *
     * Grouped by theme, and coloured by theme rather than by row: there are ten
     * colours and forty-five categories, so a unique colour each is impossible.
     * Making every food category orange-ish and every transport one blue means
     * the dashboard chart still reads as groups instead of confetti.
     *
     * Khmer is filled in for all of them — the app ships bilingual, and a
     * category with no Khmer name silently falls back to English mid-list.
     *
     * @return array<int, array{0: string, 1: string, 2: CategoryColor, 3: CategoryIcon}>
     */
    private function categories(): array
    {
        return [
            // --- Food & drink -------------------------------------------------
            ['Food', 'អាហារ', CategoryColor::Orange, CategoryIcon::Utensils],
            ['Groceries', 'គ្រឿងទេស', CategoryColor::Orange, CategoryIcon::ShoppingCart],
            ['Restaurants', 'ភោជនីយដ្ឋាន', CategoryColor::Orange, CategoryIcon::Pizza],
            ['Snacks', 'អាហារសម្រន់', CategoryColor::Orange, CategoryIcon::Sandwich],
            ['Fruit & Vegetables', 'បន្លែ និងផ្លែឈើ', CategoryColor::Green, CategoryIcon::Carrot],
            ['Salad', 'សាឡាដ', CategoryColor::Green, CategoryIcon::Salad],
            ['Meat', 'សាច់', CategoryColor::Red, CategoryIcon::Beef],
            ['Seafood', 'អាហារសមុទ្រ', CategoryColor::Teal, CategoryIcon::Fish],
            ['Dairy', 'ទឹកដោះគោ', CategoryColor::Slate, CategoryIcon::Milk],
            ['Coffee', 'កាហ្វេ', CategoryColor::Amber, CategoryIcon::Coffee],
            ['Beer', 'ស្រាបៀរ', CategoryColor::Amber, CategoryIcon::Beer],
            ['Wine', 'ស្រា', CategoryColor::Purple, CategoryIcon::Wine],

            // --- Transport ----------------------------------------------------
            ['Transport', 'ការធ្វើដំណើរ', CategoryColor::Blue, CategoryIcon::Car],
            ['Fuel', 'ប្រេងឥន្ធនៈ', CategoryColor::Blue, CategoryIcon::Fuel],
            ['Bus', 'ឡានក្រុង', CategoryColor::Blue, CategoryIcon::Bus],
            ['Train', 'រថភ្លើង', CategoryColor::Blue, CategoryIcon::TrainFront],
            ['Ferry', 'ទូក', CategoryColor::Teal, CategoryIcon::Ship],
            ['Parking', 'ចំណតរថយន្ត', CategoryColor::Slate, CategoryIcon::Car],

            // --- Home & bills -------------------------------------------------
            ['Rent', 'ថ្លៃជួលផ្ទះ', CategoryColor::Red, CategoryIcon::House],
            ['Bills', 'វិក្កយបត្រ', CategoryColor::Red, CategoryIcon::Receipt],
            ['Electricity', 'អគ្គិសនី', CategoryColor::Amber, CategoryIcon::Zap],
            ['Water', 'ទឹក', CategoryColor::Blue, CategoryIcon::Receipt],
            ['Internet', 'អ៊ីនធឺណិត', CategoryColor::Teal, CategoryIcon::Smartphone],
            ['Phone', 'ទូរស័ព្ទ', CategoryColor::Teal, CategoryIcon::Smartphone],
            ['Household', 'គ្រឿងប្រើប្រាស់ក្នុងផ្ទះ', CategoryColor::Slate, CategoryIcon::House],
            ['Repairs', 'ការជួសជុល', CategoryColor::Slate, CategoryIcon::Briefcase],

            // --- Health -------------------------------------------------------
            ['Health', 'សុខភាព', CategoryColor::Pink, CategoryIcon::Heart],
            ['Pharmacy', 'ឱសថស្ថាន', CategoryColor::Pink, CategoryIcon::Pill],
            ['Doctor', 'វេជ្ជបណ្ឌិត', CategoryColor::Pink, CategoryIcon::Stethoscope],
            ['Fitness', 'កីឡា', CategoryColor::Green, CategoryIcon::Dumbbell],

            // --- Lifestyle ----------------------------------------------------
            ['Shopping', 'ការទិញទំនិញ', CategoryColor::Purple, CategoryIcon::ShoppingBag],
            ['Entertainment', 'កម្សាន្ត', CategoryColor::Purple, CategoryIcon::Film],
            ['Music', 'តន្ត្រី', CategoryColor::Purple, CategoryIcon::Music],
            ['Games', 'ហ្គេម', CategoryColor::Purple, CategoryIcon::Gamepad2],
            // Education covers books too — a separate "Books" row would share
            // both this icon and most of its meaning.
            ['Education', 'ការសិក្សា', CategoryColor::Indigo, CategoryIcon::Book],
            ['Gifts', 'អំណោយ', CategoryColor::Pink, CategoryIcon::Gift],
            ['Pets', 'សត្វចិញ្ចឹម', CategoryColor::Amber, CategoryIcon::PawPrint],

            // --- Travel -------------------------------------------------------
            ['Travel', 'ការធ្វើដំណើរកម្សាន្ត', CategoryColor::Teal, CategoryIcon::Luggage],
            ['Flights', 'ជើងហោះហើរ', CategoryColor::Teal, CategoryIcon::Plane],
            ['Hotels', 'សណ្ឋាគារ', CategoryColor::Teal, CategoryIcon::Hotel],
            ['Holidays', 'វិស្សមកាល', CategoryColor::Teal, CategoryIcon::TreePalm],

            // --- Money --------------------------------------------------------
            ['Savings', 'ប្រាក់សន្សំ', CategoryColor::Green, CategoryIcon::PiggyBank],
            ['Loan', 'ប្រាក់កម្ចី', CategoryColor::Red, CategoryIcon::Landmark],
            ['Fees', 'កម្រៃសេវា', CategoryColor::Slate, CategoryIcon::CreditCard],

            // Kept last: the fallback for anything that fits nowhere above.
            ['Other', 'ផ្សេងៗ', CategoryColor::Slate, CategoryIcon::CircleDashed],
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
