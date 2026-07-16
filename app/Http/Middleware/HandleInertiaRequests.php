<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'is_admin' => (bool) $request->user()?->isAdmin(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => collect(Locale::cases())
                ->map(fn (Locale $locale) => [
                    'value' => $locale->value,
                    'label' => $locale->label(),
                    'short' => $locale->shortLabel(),
                ])
                ->all(),
            // The whole active-locale dictionary; it is a few KB and lets the
            // frontend resolve __() without a round trip per string.
            'translations' => fn () => $this->translations(app()->getLocale()),
        ];
    }

    /**
     * Reads lang/{locale}.json — the same file PHP's __() uses, so a key never
     * means two different things on the two sides.
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }
}
