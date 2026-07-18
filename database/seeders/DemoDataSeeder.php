<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a fresh development database needs to look like a used one.
 *
 * Kept out of DatabaseSeeder on purpose. That seeder runs on deploys, where the
 * roles, the owner account, the category list and the footer pages all have to
 * exist — but invented expenses and budgets would land in somebody's real books
 * and be indistinguishable from their own. So the reference data is automatic
 * and the pretend data is opt-in:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * It deletes the target user's existing expenses and budgets before writing, so
 * running it twice gives the same dashboard rather than double the money.
 *
 * App settings are seeded here too, and nowhere else: they are an admin's own
 * choices, so a seeder in the deploy path would undo them on every release.
 * Uploaded files are left alone — see AppSettingSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoDataSeeder refuses to run in production.');

            return;
        }

        // Idempotent, and demo data is meaningless without the users and
        // categories it hangs off — so this works on an empty database too.
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            PageSeeder::class,
            AppSettingSeeder::class,
            ExpenseSeeder::class,
            BudgetSeeder::class,
        ]);
    }
}
