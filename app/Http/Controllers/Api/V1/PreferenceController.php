<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BodyColor;
use App\Enums\ButtonColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreferencesRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Preferences
 *
 * The signed-in account's own default currency and colours. Each overrides
 * the app-wide value an admin set, for this account only; null means "follow
 * the app". Open to every account, like the profile photo: it changes how the
 * app looks and which currency a field starts on, never what anyone can do.
 *
 * @authenticated
 */
class PreferenceController extends Controller
{
    /**
     * Get preferences
     *
     * The account's own choices (null where it follows the app), the currency
     * its amount fields actually start on, and the swatches to offer.
     *
     * @response 200 {"data": {"currency": null, "button_color": "#2f6f43", "body_color": null, "default_currency": "USD", "button_presets": [], "body_presets": []}}
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->row($request->user())]);
    }

    /**
     * Update preferences
     *
     * Only the fields sent are touched; send one as null to go back to the
     * app's value.
     *
     * @bodyParam currency string USD or KHR, or null to follow the app. Example: KHR
     * @bodyParam button_color string Any #rrggbb that can carry a readable label, or null. Example: #2f6f43
     * @bodyParam body_color string One of the body presets, or null. Example: #faf8f4
     */
    public function update(PreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (array_key_exists('currency', $validated)) {
            $user->preferred_currency = $validated['currency'];
        }
        foreach (['button_color', 'body_color'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field] === null ? null : strtolower($validated[$field]);
            }
        }
        $user->save();

        return response()->json(['data' => $this->row($user->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            ...$user->preferences(),
            'default_currency' => $user->defaultCurrency()->value,
            'button_presets' => ButtonColor::presets(),
            'body_presets' => BodyColor::presets(),
        ];
    }
}
