<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's counterpart to SetLocale: a token client has no session, so the
 * language rides on the request instead. `Accept-Language: km` (the first
 * tag wins; a region suffix is ignored) picks the locale translatable
 * columns — category names, FAQs, the guidance copy — are resolved in.
 * Anything unrecognised leaves the default in place.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Accept-Language', '');
        $tag = strtolower(trim(explode(',', $header)[0]));
        $code = explode('-', $tag)[0];

        if ($code !== '' && Locale::tryFrom($code)) {
            app()->setLocale($code);
        }

        return $next($request);
    }
}
