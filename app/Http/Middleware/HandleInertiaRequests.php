<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Enums\Permission;
use App\Models\AppSetting;
use App\Models\Page;
use App\Services\BudgetSummary;
use Carbon\CarbonImmutable;
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
                // The frontend shows/hides on these; the policies still decide.
                // Sent as a flat list so a template can ask can('users.view')
                // rather than re-deriving it from the role.
                'permissions' => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // The layout warns on every page once this month's overall budget is
            // blown, so it is shared rather than asked for per controller.
            'over_budget' => fn () => $this->overBudget($request),
            // The footer links to published pages on every page, so the list of
            // them is shared globally like branding.
            'footer_pages' => fn () => $this->footerPages(),
            // Every page renders the wordmark, so this is shared rather than
            // repeated in each controller. AppSetting::current() is cached.
            'branding' => fn () => $this->branding(),
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
     * This month's overspend, or null when there is nothing to say.
     *
     * Always *this* month, never the month the Budgets page happens to be
     * browsing: the banner rides every page, and "you are over" has to mean now
     * or it means nothing.
     *
     * Gated on budgets.view because the banner is only actionable to someone who
     * can open the page it is about — telling anyone else would be a warning
     * with nowhere to go.
     *
     * @return array<string, mixed>|null
     */
    private function overBudget(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermissionTo(Permission::BudgetsView->value)) {
            return null;
        }

        return app(BudgetSummary::class)->overspendFor($user, CarbonImmutable::now());
    }

    /**
     * The published footer pages, as {slug, title} resolved to the active locale.
     * A page with no title in any locale is skipped rather than shown as a blank
     * link.
     *
     * @return array<int, array{slug: string, title: string}>
     */
    private function footerPages(): array
    {
        return Page::query()
            ->where('published', true)
            ->orderBy('slug')
            ->get()
            ->map(fn (Page $page) => ['slug' => $page->slug, 'title' => (string) $page->title])
            ->filter(fn (array $page) => $page['title'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, logo: string|null, favicon: string|null}
     */
    private function branding(): array
    {
        $settings = AppSetting::current();

        return [
            'name' => $settings->app_name,
            // The footer's copyright line — a person or company, defaulting to
            // the app name.
            'copyright' => $settings->copyrightHolder(),
            'logo' => $settings->logoUrl(),
            'favicon' => $settings->faviconUrl(),
            // The layout drops the ambient wash when a background colour has been
            // chosen, so the colour renders flat instead of tinted by it.
            'plain_background' => $settings->plainBackground(),
            // app.blade.php applies these before first paint, but that script only
            // runs on a full page load. An Inertia visit re-renders Vue without it,
            // so saving a colour would leave the page on the old one until a hard
            // refresh. Shipping them as props lets the layout re-apply on change.
            'css' => $settings->cssVariables(),
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
