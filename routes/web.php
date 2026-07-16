<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// SpendLog has no public landing page — the root is just a doorway to the app.
Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
})->name('home');

// Outside the auth group so the language can be switched from the login screen.
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('expenses', ExpenseController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // store() upserts the (category, month) slot, so no separate update route.
    Route::resource('budgets', BudgetController::class)
        ->only(['index', 'store', 'destroy']);

    /*
     * Settings. The route names stay as they were (profile.edit, password.update)
     * so existing links and tests keep working — only the URLs moved under /settings.
     */
    Route::prefix('settings')->group(function () {
        Route::redirect('/', '/settings/profile')->name('settings');

        Route::get('/profile', [SettingsController::class, 'profile'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::get('/password', [SettingsController::class, 'password'])->name('password.edit');

        // Admin only — enforced in the controller, not just hidden in the UI.
        Route::get('/branding', [SettingsController::class, 'branding'])->name('branding.edit');
        Route::post('/branding', [SettingsController::class, 'updateBranding'])->name('branding.update');

        Route::get('/colors', [SettingsController::class, 'colors'])->name('colors.edit');
        Route::post('/colors', [SettingsController::class, 'updateColors'])->name('colors.update');
    });
});

require __DIR__.'/auth.php';
