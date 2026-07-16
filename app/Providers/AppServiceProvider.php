<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();
    }

    /**
     * Laravel 11+ ships no RouteServiceProvider, so the API limiters have to be
     * declared somewhere — without an 'api' limiter, throttle:api throws.
     */
    private function configureRateLimiting(): void
    {
        // Keyed by token owner where we have one, falling back to IP for the
        // handful of unauthenticated calls.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->uuid ?: $request->ip()));

        // Login and register are throttled far harder: these are the endpoints
        // where guessing is the attack. Keyed by email *and* IP so one attacker
        // cannot lock a victim out of their own account by failing on purpose.
        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
