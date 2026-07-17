<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reading side of the FAQ: a plain, published-only list resolved to the
 * viewer's locale. Open to any signed-in user — help is not something to gate.
 */
class HelpController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Help/Index', [
            'faqs' => Faq::published()
                ->get()
                ->map(fn (Faq $faq) => [
                    'uuid' => $faq->uuid,
                    // Resolved to the active locale here, falling back to English
                    // when the reader's locale was left blank for this entry.
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ]),
        ]);
    }
}
