<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpendingRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin · Settings
 *
 * The spending settings the web keeps under Settings → Spending: the KHR/USD
 * exchange rate, the default entry currency, and the dashboard guidance copy.
 * Admin only, like the page it mirrors.
 *
 * @authenticated
 */
class SettingsAdminController extends Controller
{
    /**
     * Get spending settings
     */
    public function spending(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => $this->row(AppSetting::current())]);
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
