<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Applies the locale the user picked, stored in the session so it survives
     * navigation without needing a column on users.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if ($locale && Locale::tryFrom($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
