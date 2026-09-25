<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Railway (like any platform proxy) terminates TLS and forwards the
         * request over plain HTTP, announcing the original scheme in
         * X-Forwarded-Proto. Without trusting that header Laravel believes the
         * request is insecure: url() and redirect() emit http://, the session
         * cookie is never marked secure, and a browser on https gets a mixed
         * scheme it may refuse.
         *
         * '*' rather than a list: the proxy is inside Railway's network and its
         * address is neither fixed nor published, so there is nothing stable to
         * name. The app is only reachable through that proxy, so there is no
         * path by which an untrusted client sets these headers.
         */
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            // Before HandleInertiaRequests: it shares the active locale and its
            // dictionary, so the locale must already be applied by then.
            SetLocale::class,
            // Suspending someone who is already signed in has to end that session
            // now, not whenever it happens to expire.
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Applies the 'api' limiter to every /api route. Not on by default in
        // Laravel 11+, so without this the API would be unthrottled.
        $middleware->throttleApi();

        // The same status guard as the web group. A token carries abilities but
        // not status, so without a checkpoint here a suspended account's phone
        // keeps working indefinitely — the login check alone only covers clients
        // that come back for a new token.
        $middleware->api(append: [
            EnsureUserIsActive::class,
            // Accept-Language picks the locale for translatable columns.
            SetApiLocale::class,
        ]);

        // Sanctum ships these but registers no aliases in Laravel 11+, so
        // 'abilities:...' on a route resolves to nothing without this.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
