<?php

namespace App\Http\Controllers;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Enums\Permission;
use App\Enums\Locale;
use App\Http\Requests\BrandingRequest;
use App\Http\Requests\ColorRequest;
use App\Http\Requests\SpendingRequest;
use App\Models\AppSetting;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Where branding uploads live on the 'public' disk. */
    private const BRANDING_DIR = 'branding';

    public function profile(Request $request): Response
    {
        return Inertia::render('Settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    public function password(): Response
    {
        return Inertia::render('Settings/Password');
    }

    public function branding(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $settings = AppSetting::current();

        return Inertia::render('Settings/Branding', [
            'branding' => [
                'app_name' => $settings->app_name,
                'logo' => $settings->logoUrl(),
                'favicon' => $settings->faviconUrl(),
            ],
        ]);
    }

    public function colors(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $settings = AppSetting::current();

        return Inertia::render('Settings/Colors', [
            'colors' => [
                'button_color' => $settings->button_color,
                'body_color' => $settings->body_color,
            ],
            // Sent rather than mirrored in JS: the swatches, the migration
            // default and the enums then cannot drift apart.
            'button_presets' => ButtonColor::presets(),
            'body_presets' => BodyColor::presets(),
        ]);
    }

    public function spending(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $settings = AppSetting::current();

        return Inertia::render('Settings/Spending', [
            'spending' => [
                'enabled' => $settings->spending_guidance_enabled,
                // Both locales, always present, so the form has a box for each
                // even before anything is written.
                'warning' => $this->localeMap($settings, 'spending_warning'),
                'advice' => $this->localeMap($settings, 'spending_advice'),
            ],
            'locales' => array_map(
                fn (Locale $locale) => ['value' => $locale->value, 'label' => $locale->label()],
                Locale::cases(),
            ),
        ]);
    }

    public function updateSpending(SpendingRequest $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $settings = AppSetting::current();

        $settings->spending_guidance_enabled = $request->boolean('enabled');
        // Empty locales are dropped by translationsFor(), and an empty array
        // clears the field, so unchecking everything genuinely blanks it.
        $settings->replaceTranslations('spending_warning', $request->translationsFor('warning'));
        $settings->replaceTranslations('spending_advice', $request->translationsFor('advice'));

        $settings->save();

        return back()->with('success', __('Spending guidance updated.'));
    }

    /**
     * Every locale's stored value for a translatable field, keyed by locale, with
     * a blank string for any that is unset — the shape the settings form binds to.
     *
     * @return array<string, string>
     */
    private function localeMap(AppSetting $settings, string $field): array
    {
        $translations = $settings->getTranslations($field);

        return collect(Locale::cases())
            ->mapWithKeys(fn (Locale $locale) => [$locale->value => $translations[$locale->value] ?? ''])
            ->all();
    }

    public function updateColors(ColorRequest $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $settings = AppSetting::current();

        $settings->button_color = $request->validated('button_color');
        $settings->body_color = $request->validated('body_color');

        // saved() busts the cache, so the next request serves the new palette.
        $settings->save();

        return back()->with('success', __('Colours updated.'));
    }

    public function updateBranding(BrandingRequest $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $settings = AppSetting::current();

        $settings->app_name = $request->validated('app_name');

        $this->applyImage($request, $settings, 'logo', 'logo_path');
        $this->applyImage($request, $settings, 'favicon', 'favicon_path');

        $settings->save();

        return back()->with('success', __('Branding updated.'));
    }

    /**
     * Replace, clear, or leave an image alone — and delete whatever it replaced,
     * so the disk does not fill with orphaned uploads.
     */
    private function applyImage(BrandingRequest $request, AppSetting $settings, string $field, string $column): void
    {
        $removing = $request->boolean("remove_{$field}");
        $file = $request->file($field);

        if (! $removing && ! $file instanceof UploadedFile) {
            return;
        }

        $previous = $settings->{$column};

        $settings->{$column} = $removing
            ? null
            : $file->store(self::BRANDING_DIR, 'public');

        if ($previous && $previous !== $settings->{$column}) {
            Storage::disk('public')->delete($previous);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            (bool) $request->user()?->hasPermissionTo(Permission::SettingsBranding->value),
            403,
        );
    }
}
