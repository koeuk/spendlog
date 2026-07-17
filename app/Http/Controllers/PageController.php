<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Requests\PageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The footer's editable pages. The admin edits a fixed set (About, Privacy); the
 * reading side renders one published page. Both gated by role of the caller:
 * editing on managePages, reading open to any signed-in user.
 */
class PageController extends Controller
{
    /** The label shown for each seeded slug in the settings list. */
    private const LABELS = [
        'about' => 'About',
        'privacy' => 'Privacy Policy',
    ];

    public function index(): Response
    {
        Gate::authorize('managePages');

        return Inertia::render('Settings/Pages', [
            'pages' => Page::query()
                ->orderBy('slug')
                ->get()
                ->map(fn (Page $page) => [
                    'slug' => $page->slug,
                    'name' => self::LABELS[$page->slug] ?? $page->slug,
                    // Raw per-locale maps, both keys always present.
                    'title' => $this->localeMap($page, 'title'),
                    'body' => $this->localeMap($page, 'body'),
                    'published' => $page->published,
                ]),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        Gate::authorize('managePages');

        $page->replaceTranslations('title', $request->translationsFor('title'));
        $page->replaceTranslations('body', $request->translationsFor('body'));
        $page->published = $request->boolean('published');
        $page->save();

        return back()->with('success', __('Page saved.'));
    }

    /**
     * The public read-only page.
     *
     * The footer links here even before an admin has published the page, so a
     * draft shows a short "not published yet" placeholder rather than 404-ing —
     * and never its half-written body. Only the fixed label (About / Privacy
     * Policy) is exposed, which is not draft content.
     */
    public function show(Page $page): Response
    {
        if (! $page->published) {
            return Inertia::render('Pages/Show', [
                'page' => [
                    'title' => self::LABELS[$page->slug] ?? $page->slug,
                    'body' => __('This page has not been published yet. Please check back soon.'),
                ],
            ]);
        }

        return Inertia::render('Pages/Show', [
            'page' => [
                // Resolved to the reader's locale, falling back to English.
                'title' => $page->title,
                'body' => $page->body,
            ],
        ]);
    }

    /**
     * Both locales for a translatable field, always with every key present.
     *
     * @return array<string, string>
     */
    private function localeMap(Page $page, string $field): array
    {
        $translations = $page->getTranslations($field);

        $map = [];

        foreach (Locale::cases() as $locale) {
            $map[$locale->value] = $translations[$locale->value] ?? '';
        }

        return $map;
    }
}
