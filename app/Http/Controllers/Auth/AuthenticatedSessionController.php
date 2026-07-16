<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        // The intended URL was captured while this was still a guest, so nothing
        // had checked it against a permission yet. Replaying a bookmarked
        // /dashboard for someone who may not open it just lands them on a 403.
        if (! $user->canOpenRoute($this->intendedRouteName($request))) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route($user->homeRoute(), absolute: false));
    }

    /**
     * The route name behind the stashed intended URL, or null if there is none
     * and for anything this app does not route — an off-site or unmatched URL is
     * not ours to second-guess.
     */
    private function intendedRouteName(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        try {
            return Route::getRoutes()->match(Request::create($intended))->getName();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
