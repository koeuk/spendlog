<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

/**
 * @group Branding
 *
 * What the app looks like, for any client to read before anyone signs in:
 * the name and marks, and the colours an admin chose under Settings. Public,
 * like the same values on the web's sign-in page — none of it is secret, and
 * a client has to paint its first screen with something.
 *
 * @unauthenticated
 */
class BrandingController extends Controller
{
    /**
     * Get branding
     *
     * @response 200 {"data": {"name": "SpendLog", "copyright": "SpendLog", "logo": null, "favicon": null, "button_color": "#171717", "branded": false, "body_color": "#ffffff", "plain_background": false}}
     */
    public function __invoke(): JsonResponse
    {
        $settings = AppSetting::current();

        return response()->json(['data' => [
            'name' => (string) $settings->app_name,
            'copyright' => $settings->copyrightHolder(),
            'logo' => $settings->logoUrl(),
            'favicon' => $settings->faviconUrl(),
            'button_color' => (string) $settings->button_color,
            // False at the stock colour, which means "leave the client's own
            // accent alone" rather than "paint everything near-black".
            'branded' => $settings->button_color !== AppSetting::DEFAULT_BUTTON_COLOR,
            'body_color' => (string) $settings->body_color,
            // True for every background but White, which is the ambient look
            // a client renders its own way.
            'plain_background' => $settings->plainBackground(),
        ]]);
    }
}
