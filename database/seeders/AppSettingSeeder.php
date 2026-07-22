<?php

namespace Database\Seeders;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Enums\Currency;
use App\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Development app settings: theme, currency and the dashboard guidance copy.
 *
 * Like the rest of the demo data this is opt-in, and for a sharper reason than
 * the others. These are an admin's own choices, made through Settings — a logo,
 * a brand colour, the wording shown to every user. A seeder in the deploy path
 * would silently undo all of it on each release. So this is reachable only
 * through DemoDataSeeder, which refuses to run in production.
 *
 * Uploads are deliberately left alone. logo_path and favicon_path point at files
 * on the public disk, and there is nothing to point them at here — writing them
 * would either null an existing logo or leave a path to a file that does not
 * exist, which fileUrl() then has to swallow on every request.
 */
class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = AppSetting::current();

        $settings->app_name = 'SpendLog';
        $settings->copyright_holder = 'SpendLog';

        // Green rather than the default ink, so a seeded environment exercises
        // the branded path: --primary is overridden, and every button, active
        // nav pill and segmented control has to pick the colour up from the
        // token instead of a hardcoded near-black.
        $settings->button_color = ButtonColor::Green->value;
        // The Silver default: a flat soft-silver page with white cards. White is
        // now the opt-in that keeps the ambient wash instead.
        $settings->body_color = BodyColor::Silver->value;

        $settings->khr_per_usd = 4100;
        $settings->default_currency = Currency::Usd->value;

        // On, so the dashboard card is visible without an admin turning it on
        // first — it is easy to mistake for missing rather than disabled.
        $settings->spending_guidance_enabled = true;

        $settings->setTranslations('spending_warning', [
            'en' => 'You work hard all month, yet spend everything in just a few days. '
                .'When the money is gone, you blame your salary, your life, or your luck. '
                ."But the truth is, the problem isn't a lack of money — the problem is spending without thinking.",
            'km' => 'អ្នកខិតខំធ្វើការពេញមួយខែ ប៉ុន្តែចំណាយអស់ក្នុងរយៈពេលតែប៉ុន្មានថ្ងៃ។ '
                .'នៅពេលលុយអស់ អ្នកបន្ទោសប្រាក់ខែ ជីវិត ឬសំណាងរបស់អ្នក។ '
                .'ប៉ុន្តែការពិត បញ្ហាមិនមែនជាការខ្វះលុយទេ — បញ្ហាគឺការចំណាយដោយមិនគិត។',
        ]);

        $settings->setTranslations('spending_advice', [
            'en' => 'Before you buy, wait a day. Most of what you wanted yesterday you will not want tomorrow.',
            'km' => 'មុននឹងទិញ សូមរង់ចាំមួយថ្ងៃ។ របស់ភាគច្រើនដែលអ្នកចង់បានម្សិលមិញ អ្នកនឹងលែងចង់បាននៅថ្ងៃស្អែក។',
        ]);

        // saved() busts the cache, so the next request serves these rather than
        // the row that was read before the seed.
        $settings->save();

        $this->command?->info('AppSettingSeeder: theme, currency and guidance seeded (uploads untouched).');
    }
}
