<?php

namespace Database\Seeders;

use App\Enums\FaqStatus;
use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Real content for the public pages: About, the Privacy Policy, and five
 * Help / FAQ entries, published and in both locales.
 *
 * Unlike PageSeeder — deploy-safe starter copy that never overwrites — this
 * one *replaces* whatever is there and publishes the result. It is demo
 * content for a development install, reached by hand:
 *
 *     php artisan db:seed --class=ContentSeeder
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('ContentSeeder refuses to run in production.');

            return;
        }

        $this->seedPages();
        $this->seedFaqs();

        $this->command?->info('ContentSeeder: About + Privacy published, 5 FAQ entries.');
    }

    private function seedPages(): void
    {
        $pages = [
            'about' => [
                'title' => ['en' => 'About', 'km' => 'អំពី'],
                'body' => [
                    'en' => "SpendLog is a small, fast expense tracker built for everyday life in Cambodia.\n\n"
                        .'Log what you spend the moment you spend it — a coffee, a tank of fuel, the rent — '
                        .'in dollars or riel. SpendLog keeps every entry in one place, shows where the money '
                        ."actually goes each month, and lets you set budgets before you overshoot them.\n\n"
                        .'It works in English and Khmer, on your phone and on your desk. Your data belongs '
                        .'to you: log it, browse it, and export your story whenever you need it.',
                    'km' => "SpendLog គឺជាកម្មវិធីតាមដានការចំណាយតូចមួយ លឿន បង្កើតឡើងសម្រាប់ជីវិតប្រចាំថ្ងៃនៅកម្ពុជា។\n\n"
                        .'កត់ត្រាការចំណាយភ្លាមៗពេលអ្នកចំណាយ — កាហ្វេមួយកែវ សាំងមួយធុង ថ្លៃជួលផ្ទះ — '
                        .'ជាដុល្លារ ឬជារៀល។ SpendLog រក្សាទុករាល់ការកត់ត្រានៅកន្លែងតែមួយ បង្ហាញថាលុយពិតជាទៅណាខ្លះក្នុងមួយខែ '
                        ."និងអនុញ្ញាតឱ្យអ្នកកំណត់ថវិកាមុនពេលចំណាយលើស។\n\n"
                        .'វាដំណើរការជាភាសាអង់គ្លេស និងខ្មែរ នៅលើទូរស័ព្ទ និងកុំព្យូទ័ររបស់អ្នក។ ទិន្នន័យរបស់អ្នកជាកម្មសិទ្ធិរបស់អ្នក៖ '
                        .'កត់ត្រា រកមើល និងនាំចេញនៅពេលណាដែលអ្នកត្រូវការ។',
                ],
            ],
            'privacy' => [
                'title' => ['en' => 'Privacy Policy', 'km' => 'គោលការណ៍ឯកជនភាព'],
                'body' => [
                    'en' => "Your expenses are yours. This policy explains what SpendLog stores and why.\n\n"
                        .'What we store: the expenses, budgets and categories you enter, and the account '
                        .'details you sign up with — your name, email address and, if you use it, your '
                        ."Google sign-in.\n\n"
                        .'What we do with it: show it back to you. Your spending history powers your own '
                        .'dashboard, reports and budgets, and nothing else. We do not sell your data, '
                        ."share it with advertisers, or use it to profile you.\n\n"
                        .'Who can see it: you, and the administrators of this installation. Admins can '
                        ."see expense listings for account management, not your password.\n\n"
                        .'Your choices: you can edit or delete any expense at any time. If you want your '
                        .'whole account and its data removed, contact an administrator and it will be '
                        .'deleted permanently.',
                    'km' => "ការចំណាយរបស់អ្នកគឺជារបស់អ្នក។ គោលការណ៍នេះពន្យល់ពីអ្វីដែល SpendLog រក្សាទុក និងហេតុអ្វី។\n\n"
                        .'អ្វីដែលយើងរក្សាទុក៖ ការចំណាយ ថវិកា និងប្រភេទដែលអ្នកបញ្ចូល ព្រមទាំងព័ត៌មានគណនី '
                        ."ដែលអ្នកចុះឈ្មោះ — ឈ្មោះ អ៊ីមែល និងការចូលដោយ Google ប្រសិនបើអ្នកប្រើ។\n\n"
                        .'អ្វីដែលយើងធ្វើជាមួយវា៖ បង្ហាញវាមកអ្នកវិញ។ ប្រវត្តិចំណាយរបស់អ្នកផ្តល់ថាមពលដល់ផ្ទាំងគ្រប់គ្រង '
                        .'របាយការណ៍ និងថវិការបស់អ្នកផ្ទាល់ប៉ុណ្ណោះ។ យើងមិនលក់ទិន្នន័យរបស់អ្នក '
                        ."មិនចែករំលែកជាមួយអ្នកផ្សាយពាណិជ្ជកម្ម ឬប្រើដើម្បីវិភាគអ្នកឡើយ។\n\n"
                        .'អ្នកណាអាចមើលឃើញ៖ អ្នក និងអ្នកគ្រប់គ្រងនៃការដំឡើងនេះ។ អ្នកគ្រប់គ្រងអាចមើលបញ្ជីចំណាយ '
                        ."សម្រាប់ការគ្រប់គ្រងគណនី ប៉ុន្តែមិនមែនពាក្យសម្ងាត់របស់អ្នកទេ។\n\n"
                        .'ជម្រើសរបស់អ្នក៖ អ្នកអាចកែ ឬលុបការចំណាយណាមួយបានគ្រប់ពេល។ ប្រសិនបើអ្នកចង់លុបគណនី '
                        .'និងទិន្នន័យទាំងអស់ សូមទាក់ទងអ្នកគ្រប់គ្រង ហើយវានឹងត្រូវលុបជាអចិន្ត្រៃយ៍។',
                ],
            ],
        ];

        foreach ($pages as $slug => $content) {
            $page = Page::where('slug', $slug)->first();

            if (! $page) {
                $this->command?->warn(sprintf('ContentSeeder: no "%s" page row — skipped.', $slug));

                continue;
            }

            $page->setTranslations('title', $content['title']);
            $page->setTranslations('body', $content['body']);
            // Published straight away — this is demo content meant to be seen,
            // not PageSeeder's draft awaiting an admin's read.
            $page->published = true;
            $page->save();
        }
    }

    private function seedFaqs(): void
    {
        $faqs = [
            [
                'question' => [
                    'en' => 'How do I add an expense?',
                    'km' => 'តើខ្ញុំបន្ថែមការចំណាយដោយរបៀបណា?',
                ],
                'answer' => [
                    'en' => 'Open Expenses and press "Add expense" — on a phone it is the round button above the tab bar. '
                        .'Give it a name, a price, a category and a date, then save. The list and the dashboard update immediately.',
                    'km' => 'បើកទំព័រ "ការចំណាយ" ហើយចុច "បន្ថែមការចំណាយ" — នៅលើទូរស័ព្ទ វាជាប៊ូតុងមូលនៅពីលើរបារផ្ទាំង។ '
                        .'បញ្ចូលឈ្មោះ តម្លៃ ប្រភេទ និងកាលបរិច្ឆេទ រួចរក្សាទុក។ បញ្ជី និងផ្ទាំងគ្រប់គ្រងនឹងធ្វើបច្ចុប្បន្នភាពភ្លាមៗ។',
                ],
            ],
            [
                'question' => [
                    'en' => 'Can I enter amounts in riel?',
                    'km' => 'តើខ្ញុំអាចបញ្ចូលចំនួនជារៀលបានទេ?',
                ],
                'answer' => [
                    'en' => 'Yes. Flip the currency toggle on the price field to KHR and type the riel amount. '
                        .'It is stored in US dollars at the configured exchange rate, and every screen shows both figures side by side.',
                    'km' => 'បាន។ ប្តូរប៊ូតុងរូបិយប័ណ្ណនៅលើប្រអប់តម្លៃទៅ KHR ហើយវាយចំនួនជារៀល។ '
                        .'វាត្រូវបានរក្សាទុកជាដុល្លារអាមេរិកតាមអត្រាប្តូរប្រាក់ដែលបានកំណត់ ហើយគ្រប់ទំព័របង្ហាញតួលេខទាំងពីរជាមួយគ្នា។',
                ],
            ],
            [
                'question' => [
                    'en' => 'How do budgets work?',
                    'km' => 'តើថវិកាដំណើរការយ៉ាងដូចម្តេច?',
                ],
                'answer' => [
                    'en' => 'On the Budgets page, set one overall monthly budget, a budget per category, or both. '
                        .'The bars fill as you spend, turn amber as you get close, and red when you go over — '
                        .'and the month picker lets you review any month in your history.',
                    'km' => 'នៅទំព័រ "ថវិកា" កំណត់ថវិកាប្រចាំខែសរុបមួយ ថវិកាតាមប្រភេទ ឬទាំងពីរ។ '
                        .'របារនឹងពេញនៅពេលអ្នកចំណាយ ប្រែពណ៌លឿងពេលជិតដល់ និងក្រហមពេលលើស — '
                        .'ហើយឧបករណ៍ជ្រើសខែអនុញ្ញាតឱ្យអ្នកពិនិត្យខែណាមួយក្នុងប្រវត្តិរបស់អ្នក។',
                ],
            ],
            [
                'question' => [
                    'en' => 'Who can see my expenses?',
                    'km' => 'តើអ្នកណាអាចមើលឃើញការចំណាយរបស់ខ្ញុំ?',
                ],
                'answer' => [
                    'en' => 'By default, only you. Administrators of the installation can additionally open an '
                        .'"Everyone" view for account management. Nobody outside this installation has access to your data.',
                    'km' => 'តាមលំនាំដើម មានតែអ្នកប៉ុណ្ណោះ។ អ្នកគ្រប់គ្រងនៃការដំឡើងអាចបើកទិដ្ឋភាព "ទាំងអស់គ្នា" '
                        .'បន្ថែមសម្រាប់ការគ្រប់គ្រងគណនី។ គ្មាននរណាម្នាក់ក្រៅពីការដំឡើងនេះអាចចូលប្រើទិន្នន័យរបស់អ្នកបានទេ។',
                ],
            ],
            [
                'question' => [
                    'en' => 'How do I switch between English and Khmer?',
                    'km' => 'តើខ្ញុំប្តូររវាងភាសាអង់គ្លេស និងខ្មែរដោយរបៀបណា?',
                ],
                'answer' => [
                    'en' => 'Use the EN / KM toggle in the header — the whole interface switches instantly, '
                        .'and your choice is remembered for your next visit. Categories and pages show their Khmer names too.',
                    'km' => 'ប្រើប៊ូតុង EN / KM នៅក្នុងក្បាលទំព័រ — ចំណុចប្រទាក់ទាំងមូលប្តូរភ្លាមៗ '
                        .'ហើយជម្រើសរបស់អ្នកត្រូវបានចងចាំសម្រាប់ការចូលមើលលើកក្រោយ។ ប្រភេទ និងទំព័របង្ហាញឈ្មោះខ្មែរផងដែរ។',
                ],
            ],
        ];

        // Replaced wholesale, so re-running gives these five and not five more
        // stacked on whatever test entries were lying around.
        Faq::query()->delete();

        foreach ($faqs as $position => $faq) {
            Faq::create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'status' => FaqStatus::Published->value,
                'position' => $position + 1,
            ]);
        }
    }
}
