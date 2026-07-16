<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Stores the chosen locale in the session; SetLocale applies it on the
     * next request. Kept outside the auth group so the login page can switch too.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_column(Locale::cases(), 'value'))],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return redirect()->back();
    }
}
