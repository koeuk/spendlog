<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaqRequest;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · FAQs
 *
 * The Help page's entries. Reading is open to any token — the Help screen is
 * for everyone; writing needs `settings:write` plus the manageFaqs gate.
 *
 * @authenticated
 */
class FaqAdminController extends Controller
{
    /**
     * List FAQs
     *
     * Every entry for an admin (drafts included, `status` says which); only
     * published ones for everyone else — the same split the web makes between
     * the settings list and the Help page.
     */
    public function index(): JsonResponse
    {
        $all = Gate::allows('manageFaqs');

        $faqs = Faq::query()
            ->when(! $all, fn ($query) => $query->where('status', 'published'))
            ->orderBy('position')
            ->get();

        return response()->json([
            'data' => $faqs->map($this->row(...))->all(),
        ]);
    }

    /**
     * Create an FAQ
     *
     * @bodyParam question string required Example: How do budgets work?
     * @bodyParam answer string required Example: One slot per category per month.
     * @bodyParam status string required draft or published. Example: published
     */
    public function store(FaqRequest $request): JsonResponse
    {
        Gate::authorize('manageFaqs');

        $faq = Faq::create([
            'question' => $request->translationsFor('question'),
            'answer' => $request->translationsFor('answer'),
            'status' => $request->input('status'),
            // New entries land at the bottom of the list.
            'position' => (int) Faq::max('position') + 1,
        ]);

        return response()->json(['data' => $this->row($faq)], 201);
    }

    /**
     * Update an FAQ
     */
    public function update(FaqRequest $request, Faq $faq): JsonResponse
    {
        Gate::authorize('manageFaqs');

        // replaceTranslations, not update: it clears a locale the admin blanked
        // rather than leaving the old value behind under an absent key.
        $faq->replaceTranslations('question', $request->translationsFor('question'));
        $faq->replaceTranslations('answer', $request->translationsFor('answer'));
        $faq->status = $request->input('status');
        $faq->save();

        return response()->json(['data' => $this->row($faq)]);
    }

    /**
     * Delete an FAQ
     *
     * @response 204 scenario=deleted {}
     */
    public function destroy(Faq $faq): JsonResponse
    {
        Gate::authorize('manageFaqs');

        $faq->delete();

        return response()->json([], 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Faq $faq): array
    {
        return [
            'uuid' => $faq->uuid,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'status' => $faq->status?->value ?? (string) $faq->status,
            'position' => $faq->position,
        ];
    }
}
