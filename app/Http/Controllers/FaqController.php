<?php

namespace App\Http\Controllers;

use App\Enums\FaqStatus;
use App\Enums\Locale;
use App\Http\Requests\FaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin side of the help page: authoring, ordering and publishing FAQ
 * entries. The reading side lives in HelpController.
 *
 * Every action is gated on manageFaqs (the settings.faq permission), so an
 * admin can hand this desk to someone without making them an admin.
 */
class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manageFaqs');

        return Inertia::render('Settings/Faqs', [
            'faqs' => Faq::query()
                ->orderBy('position')
                ->get()
                ->map(fn (Faq $faq) => [
                    'uuid' => $faq->uuid,
                    // Raw per-locale maps, always with both keys, so the form
                    // has a box for each even before anything is written.
                    'question' => $this->localeMap($faq, 'question'),
                    'answer' => $this->localeMap($faq, 'answer'),
                    'status' => $faq->status->value,
                ]),
            'statuses' => $this->statusOptions(),
            // Locales come from the globally shared list (LocaleTabs reads it).
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manageFaqs');

        return Inertia::render('Settings/FaqForm', [
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function edit(Faq $faq): Response
    {
        Gate::authorize('manageFaqs');

        return Inertia::render('Settings/FaqForm', [
            'faq' => [
                'uuid' => $faq->uuid,
                // Raw per-locale maps, always with both keys, so the form has a
                // box for each even before anything is written.
                'question' => $this->localeMap($faq, 'question'),
                'answer' => $this->localeMap($faq, 'answer'),
                'status' => $faq->status->value,
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        Gate::authorize('manageFaqs');

        Faq::create([
            'question' => $request->translationsFor('question'),
            'answer' => $request->translationsFor('answer'),
            'status' => $request->input('status'),
            // New entries land at the bottom of the list.
            'position' => (int) Faq::max('position') + 1,
        ]);

        // Not back(): the form is its own page, so back() would land on
        // the form that was just submitted.
        return redirect()->route('faqs.index')->with('success', __('FAQ entry created.'));
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        Gate::authorize('manageFaqs');

        // replaceTranslations, not update: it clears a locale the admin blanked
        // rather than leaving the old value behind under an absent key.
        $faq->replaceTranslations('question', $request->translationsFor('question'));
        $faq->replaceTranslations('answer', $request->translationsFor('answer'));
        $faq->status = $request->input('status');
        $faq->save();

        // Not back(): the form is its own page, so back() would land on
        // the form that was just submitted.
        return redirect()->route('faqs.index')->with('success', __('FAQ entry updated.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        Gate::authorize('manageFaqs');

        $faq->delete();

        return back()->with('success', __('FAQ entry deleted.'));
    }

    /**
     * Persist a new reading order. Takes the full list of uuids in the order the
     * admin dragged them into, and rewrites every position in one transaction so
     * the set stays gap-free and no two rows collide.
     */
    public function reorder(Request $request): RedirectResponse
    {
        Gate::authorize('manageFaqs');

        $validated = $request->validate([
            'uuids' => ['required', 'array'],
            'uuids.*' => ['string', Rule::exists('faqs', 'uuid')],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['uuids'] as $position => $uuid) {
                Faq::where('uuid', $uuid)->update(['position' => $position]);
            }
        });

        return back()->with('success', __('Order saved.'));
    }

    /**
     * The publish states, shared by the list and the form.
     *
     * @return array<int, array<string, string>>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (FaqStatus $status) => ['value' => $status->value, 'label' => $status->label()],
            FaqStatus::cases(),
        );
    }

    /**
     * Both locales for a translatable field, always with every key present, so
     * the admin form renders an empty box for a language rather than omitting it.
     *
     * @return array<string, string>
     */
    private function localeMap(Faq $faq, string $field): array
    {
        $translations = $faq->getTranslations($field);

        $map = [];

        foreach (Locale::cases() as $locale) {
            $map[$locale->value] = $translations[$locale->value] ?? '';
        }

        return $map;
    }
}
