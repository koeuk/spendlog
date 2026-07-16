<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scribe adds its docs route with no middleware, which would leave /docs — every
 * endpoint, payload shape and example, plus a Try It Out button that fires real
 * requests — readable by anyone who guesses the URL.
 *
 * Open in local so it is frictionless while developing; admins only elsewhere.
 */
class RestrictDocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        if (Gate::allows('viewApiDocs')) {
            return $next($request);
        }

        abort(403);
    }
}
