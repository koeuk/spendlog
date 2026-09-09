<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandingRequest;
use App\Http\Requests\ColorRequest;
use App\Http\Requests\SpendingRequest;
use App\Models\AppSetting;
use App\Support\Concerns\StoresBrandingImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin · Settings
 *
 * The settings the web keeps under Settings → Spending, Appearance and
 * Colours: the KHR/USD exchange rate, the default entry currency, the
 * dashboard guidance copy, the app name and marks, and the palette. Admin
 * only, like the pages they mirror.
 *
 * @authenticated
 */
class SettingsAdminController extends Controller
{
    use StoresBrandingImages;

    /**
     * Get spending settings
     */
    public function spending(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => $this->row(AppSetting::current())]);
    }

    /**
     * Money settings
     *
     * The two figures a client needs to *enter* money, readable by any
     * signed-in account: the rate a riel amount converts at, and which
     * currency the amount fields start on.
     *
     * Not admin-only, though only an admin may change them. Without the rate
     * a client cannot show what "៛" it is about to send, so switching an
     * amount field to riel had to clear it rather than convert — a client
     * that cannot read the rate can only guess, and guessing at money is
     * worse than asking again.
     *
     * @response 200 {"data": {"khr_per_usd": 4100, "default_currency": "USD"}}
     */
    public function money(): JsonResponse
    {
        $settings = AppSetting::current();

        return response()->json(['data' => [
            'khr_per_usd' => (float) $settings->khrPerUsd(),
            'default_currency' => $settings->defaultCurrency()->value,
        ]]);
    }

    /**
     * Update spending settings
     *
     * Fields are optional except the toggle — omitting the rate leaves it
     * unchanged rather than clearing it, matching the web form.
     *
     * @bodyParam enabled boolean required Whether the dashboard guidance card shows. Example: true
     * @bodyParam warning string Shown when over budget. Example: Spend less.
     * @bodyParam advice string Shown otherwise. Example: Save first.
     * @bodyParam khr_per_usd number The exchange rate every ៛ entry converts at. Example: 4100
     * @bodyParam default_currency string USD or KHR — which the amount fields start on. Example: USD
     */
    public function updateSpending(SpendingRequest $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        AppSetting::current()->update($request->spendingAttributes());

        return response()->json(['data' => $this->row(AppSetting::current()->fresh())]);
    }

    /**
     * Get branding
     *
     * The app name, the footer's copyright holder, and the logo and favicon
     * as absolute URLs (or null when unset).
     */
    public function branding(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => $this->brandingRow(AppSetting::current())]);
    }

    /**
     * Update branding
     *
     * Multipart, since it carries files. Same rules as the web form: SVG is
     * refused, the favicon is squared and shrunk on the way in, and each
     * image is replaced or cleared independently — omit both the file and
     * the remove flag to leave one alone.
     *
     * @bodyParam app_name string required Example: SpendLog
     * @bodyParam copyright_holder string Blank falls back to the app name. Example: SpendLog Ltd
     * @bodyParam logo file PNG, JPG or WebP up to 2 MB.
     * @bodyParam favicon file PNG, ICO, JPG or WebP up to 1 MB.
     * @bodyParam remove_logo boolean Clear the logo. Example: false
     * @bodyParam remove_favicon boolean Clear the favicon. Example: false
     */
    public function updateBranding(BrandingRequest $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $settings = AppSetting::current();

        $settings->app_name = $request->validated('app_name');
        $settings->copyright_holder = trim((string) $request->validated('copyright_holder')) ?: null;

        $this->applyImage($request, $settings, 'logo', 'logo_path');
        $this->applyImage($request, $settings, 'favicon', 'favicon_path');

        $settings->save();

        return response()->json(['data' => $this->brandingRow(AppSetting::current()->fresh())]);
    }

    /**
     * Get colours
     *
     * The button and background colours, with the presets the web page offers
     * so a client can draw the same swatches.
     */
    public function colors(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => $this->colorRow(AppSetting::current())]);
    }

    /**
     * Update colours
     *
     * @bodyParam button_color string required Any #rrggbb that can carry a readable label. Example: #2f6f43
     * @bodyParam body_color string required One of the body presets. Example: #ffffff
     */
    public function updateColors(ColorRequest $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $settings = AppSetting::current();

        $settings->button_color = $request->validated('button_color');
        $settings->body_color = $request->validated('body_color');
        $settings->save();

        return response()->json(['data' => $this->colorRow(AppSetting::current()->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function brandingRow(AppSetting $settings): array
    {
        return [
            'app_name' => (string) $settings->app_name,
            'copyright_holder' => $settings->copyright_holder,
            'logo' => $settings->logoUrl(),
            'favicon' => $settings->faviconUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function colorRow(AppSetting $settings): array
    {
        return [
            'button_color' => (string) $settings->button_color,
            'body_color' => (string) $settings->body_color,
            'button_presets' => ButtonColor::presets(),
            'body_presets' => BodyColor::presets(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AppSetting $settings): array
    {
        return [
            'khr_per_usd' => (float) $settings->khrPerUsd(),
            'default_currency' => $settings->default_currency?->value ?? 'USD',
            'spending_guidance_enabled' => (bool) $settings->spending_guidance_enabled,
            'spending_warning' => (string) $settings->spending_warning,
            'spending_advice' => (string) $settings->spending_advice,
        ];
    }
}
