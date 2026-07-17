<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Starter copy for the footer pages, in both locales.
     *
     * A template, not a live page: everything here lands as a draft, so nothing
     * shows in the footer until an admin has read it and turned it on. The copy
     * is deliberately generic — an About and a Privacy skeleton to edit, not a
     * legal document to trust as-is.
     *
     * @return array<string, array{title: array<string,string>, body: array<string,string>}>
     */
    private function pages(): array
    {
        return [
            'about' => [
                'title' => [
                    'en' => 'About',
                    'km' => 'អំពី',
                ],
                'body' => [
                    'en' => "MoneyLog helps you log what you spend the moment you spend it, "
                        ."see where the money actually goes each month, and set a budget "
                        ."before you overshoot it.\n\n"
                        ."Replace this text with your own story — who built it, why, and "
                        ."how to get in touch.",
                    'km' => "MoneyLog ជួយអ្នកកត់ត្រាការចំណាយភ្លាមៗពេលអ្នកចំណាយ "
                        ."មើលថាលុយពិតជាទៅណាខ្លះក្នុងមួយខែ និងកំណត់ថវិកាមុនពេលចំណាយលើស។\n\n"
                        ."សូមជំនួសអត្ថបទនេះដោយរឿងរបស់អ្នក — អ្នកណាបង្កើត ហេតុអ្វី "
                        ."និងវិធីទាក់ទង។",
                ],
            ],
            'privacy' => [
                'title' => [
                    'en' => 'Privacy Policy',
                    'km' => 'គោលការណ៍ឯកជនភាព',
                ],
                'body' => [
                    'en' => "Your expenses are yours. This is a starting template — replace it "
                        ."with your own policy.\n\n"
                        ."What we store: the expenses, budgets and categories you enter, and "
                        ."the account details you sign up with.\n\n"
                        ."What we do with it: show it back to you. We do not sell your data.\n\n"
                        ."Contact us if you want your account and its data deleted.",
                    'km' => "ការចំណាយរបស់អ្នកគឺជារបស់អ្នក។ នេះជាគំរូចាប់ផ្តើម — "
                        ."សូមជំនួសដោយគោលការណ៍របស់អ្នក។\n\n"
                        ."អ្វីដែលយើងរក្សាទុក៖ ការចំណាយ ថវិកា និងប្រភេទដែលអ្នកបញ្ចូល "
                        ."ព្រមទាំងព័ត៌មានគណនីដែលអ្នកចុះឈ្មោះ។\n\n"
                        ."អ្វីដែលយើងធ្វើជាមួយវា៖ បង្ហាញវាមកអ្នកវិញ។ យើងមិនលក់ទិន្នន័យរបស់អ្នកទេ។\n\n"
                        ."សូមទាក់ទងយើងប្រសិនបើអ្នកចង់លុបគណនី និងទិន្នន័យរបស់វា។",
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach ($this->pages() as $slug => $content) {
            $page = Page::where('slug', $slug)->first();

            // Only fill a page that is still blank. Additive on purpose: this runs
            // on every deploy, and must never overwrite copy an admin has edited.
            if (! $page || $page->getTranslation('title', 'en', false) !== '') {
                continue;
            }

            $page->setTranslations('title', $content['title']);
            $page->setTranslations('body', $content['body']);
            // Left as a draft — an admin publishes it after a read.
            $page->save();
        }
    }
}
