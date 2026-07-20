<?php

namespace App\Http\Controllers;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Enums\WeightUnit;
use App\Http\Requests\BrandingRequest;
use App\Http\Requests\ColorRequest;
use App\Http\Requests\SpendingRequest;
use App\Models\AppSetting;
use App\Models\Workout;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
                // The stored value, not the fallback — the form shows blank when
                // unset so the placeholder can hint the default.
                'copyright_holder' => $settings->copyright_holder,
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
                // The raw JSON maps, exactly as CategoryController sends
                // category.name. The form seeds both locales off them.
                'warning' => $settings->getTranslations('spending_warning'),
                'advice' => $settings->getTranslations('spending_advice'),
                'khr_per_usd' => $settings->khrPerUsd(),
                'default_currency' => $settings->defaultCurrency()->value,
            ],
            // Value/label pairs rather than a bare enum dump, so the select can
            // show the symbol without the page knowing what a Currency is.
            'currencies' => collect(Currency::cases())
                ->map(fn (Currency $currency) => [
                    'value' => $currency->value,
                    'label' => $currency->symbol().' '.$currency->value,
                ])
                ->all(),
        ]);
    }

    /**
     * The exercise module's own preferences.
     *
     * Gated on exercise.view rather than the admin check the other settings
     * pages use: the module is granted per person, and someone who has it needs
     * to be able to set their unit without being an admin. The value itself is
     * app-wide (it lives on app_settings beside default_currency), so the page
     * is only offered to people who hold the module at all.
     */
    public function exercise(Request $request): Response
    {
        Gate::authorize('viewAny', Workout::class);

        return Inertia::render('Settings/Exercise', [
            'exercise' => [
                'default_weight_unit' => AppSetting::current()->defaultWeightUnit()->value,
            ],
            'units' => WeightUnit::options(),
        ]);
    }

    public function updateExercise(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Workout::class);

        $validated = $request->validate([
            'default_weight_unit' => ['required', Rule::enum(WeightUnit::class)],
        ]);

        AppSetting::current()->update($validated);

        return back()->with('success', __('Exercise preferences updated.'));
    }

    public function updateSpending(SpendingRequest $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        // Mass-assigned like Category::update — spatie turns the per-locale
        // arrays into the JSON columns, and blank locales are already dropped.
        AppSetting::current()->update($request->spendingAttributes());

        return back()->with('success', __('Spending guidance updated.'));
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
        // Trimmed to null when blank, so copyrightHolder() falls back cleanly.
        $settings->copyright_holder = trim((string) $request->validated('copyright_holder')) ?: null;

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

        $settings->{$column} = match (true) {
            $removing => null,
            $field === 'favicon' => $this->storeFavicon($file),
            default => $file->store(self::BRANDING_DIR, 'public'),
        };

        if ($previous && $previous !== $settings->{$column}) {
            Storage::disk('public')->delete($previous);
        }
    }

    /**
     * Store a favicon as a small square PNG, whatever was handed to us.
     *
     * Stored verbatim, an upload is whatever came off the admin's camera roll —
     * the one that prompted this was a 965x1240 progressive JPEG of 471KB, with
     * the EXIF still on it. Nothing rejects that: it is a valid image, it saves,
     * it serves 200, and it appears on the branding page, so the only symptom is
     * a tab that keeps the default mark. A favicon is a 16px square by the time
     * anyone sees it, and browsers are far narrower about what they will decode
     * for that slot than about what they will draw in a page.
     *
     * So it is converted rather than validated: telling an admin their logo is
     * the wrong shape is a worse answer than cropping it, and the crop is from
     * the centre, which is where the subject of a square-ish logo is.
     */
    private function storeFavicon(UploadedFile $file): string
    {
        // ICO is already an icon — and GD cannot read it anyway.
        if (strtolower((string) $file->getClientOriginalExtension()) === 'ico') {
            return $file->store(self::BRANDING_DIR, 'public');
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        // Unreadable by GD but past validation — store it as-is rather than
        // losing the upload. Same outcome as before this method existed.
        if ($source === false) {
            return $file->store(self::BRANDING_DIR, 'public');
        }

        // 64, not 16: browsers pick from what they are given and scale down, and
        // a retina tab or a bookmark bar asks for more than 16.
        $size = 64;
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor($size, $size);
        // Or the transparency in a PNG logo fills with black.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $size, $size,
            $side, $side,
        );

        ob_start();
        imagepng($canvas, null, 9);
        $png = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        $path = self::BRANDING_DIR.'/'.Str::random(40).'.png';
        Storage::disk('public')->put($path, $png);

        return $path;
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            (bool) $request->user()?->hasPermissionTo(Permission::SettingsBranding->value),
            403,
        );
    }
}
