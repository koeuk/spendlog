<?php

namespace App\Http\Controllers;

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
                    // One box per field, resolved for the reader's locale — the
                    // editor no longer has a tab per language.
                    'title' => $page->title,
                    'body' => $page->body,
                    'published' => $page->published,
                ]),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        Gate::authorize('managePages');

        $attributes = $request->pageAttributes();

        // Not $page->update(): mass-assigning a translations array funnels
        // through spatie's setTranslations, which merges into the stored map —
        // a locale the editor does not show would survive the save as a stale
        // second version of the copy. Replace, so the one box is the whole field.
        $page->replaceTranslations('title', $attributes['title'])
            ->replaceTranslations('body', $attributes['body'])
            ->fill(['published' => $attributes['published']])
            ->save();

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
                    // The slug is fixed and public (it is the URL), so the view
                    // can pick its icon from it even for a draft.
                    'slug' => $page->slug,
                    'title' => self::LABELS[$page->slug] ?? $page->slug,
                    'body' => __('This page has not been published yet. Please check back soon.'),
                ],
            ]);
        }

        return Inertia::render('Pages/Show', [
            'page' => [
                'slug' => $page->slug,
                // Resolved to the reader's locale, falling back to English.
                'title' => $page->title,
                'body' => $page->body,
            ],
        ]);
    }
}
