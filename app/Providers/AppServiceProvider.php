<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        // Consulted by App\Http\Middleware\RestrictDocsAccess, which guards
        // Scribe's /docs route. Admins only: the page lists every endpoint and
        // payload shape and carries a Try It Out button that fires real
        // requests — a map worth not handing out.
        Gate::define('viewApiDocs', fn (?User $user) => (bool) $user?->isAdmin());

        /*
         * Page- and self-service gates.
         *
         * These have no model to hang a policy off — they guard a whole page, or
         * an action on your own account — so they are plain abilities mapping
         * one-to-one onto a permission.
         */
        Gate::define('viewDashboard', fn (User $user) => $user->hasPermissionTo(Permission::DashboardView->value));
        Gate::define('viewReports', fn (User $user) => $user->hasPermissionTo(Permission::ReportsView->value));
        Gate::define('updateProfile', fn (User $user) => $user->hasPermissionTo(Permission::ProfileUpdate->value));
        Gate::define('updatePassword', fn (User $user) => $user->hasPermissionTo(Permission::PasswordUpdate->value));
        Gate::define('manageFaqs', fn (User $user) => $user->hasPermissionTo(Permission::SettingsFaq->value));
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
