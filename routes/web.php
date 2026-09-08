<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// SpendLog has no public landing page — the root is just a doorway to the app.
Route::get('/', function () {
    return redirect()->route(Auth::check() ? Auth::user()->homeRoute() : 'login');
})->name('home');

// Outside the auth group so the language can be switched from the login screen.
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Own screens: the colour and icon grids are some fifty tiles between them,
    // which overran a bottom sheet with the submit button below the fold.
    Route::resource('categories', CategoryController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    // create/edit are real screens rather than a dialog on the index: the form
    // opens a category picker and a calendar of its own, and a popover inside a
    // modal has nowhere to go on a phone. Routes also restore the system back
    // button, which a dialog cannot — it holds no history entry.
    Route::resource('expenses', ExpenseController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->name('reports.export');

    // store() upserts the (category, month) slot, so no separate update route.
    Route::resource('budgets', BudgetController::class)
        ->only(['index', 'store', 'destroy']);

    // Income sits beside expenses: the same own-screen forms, for the same
    // reason — the amount field opens a calendar, which a dialog cannot hold
    // on a phone.
    Route::resource('incomes', IncomeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    /*
     * Savings goals, each with a ledger. The parameter is named `goal` so the
     * controller reads as it does in the API. The ledger routes are declared
     * after the resource: `savings/{goal}/entries/...` cannot collide with
     * `savings/create`, but keeping them together says what they belong to.
     */
    Route::resource('savings', SavingsController::class)
        ->parameters(['savings' => 'goal'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/savings/{goal}/entries/create', [SavingsController::class, 'createEntry'])->name('savings.entries.create');
    Route::post('/savings/{goal}/entries', [SavingsController::class, 'storeEntry'])->name('savings.entries.store');
    // Scoped so an entry uuid from another goal is a 404 rather than a hit.
    Route::delete('/savings/{goal}/entries/{entry}', [SavingsController::class, 'destroyEntry'])
        ->scopeBindings()
        ->name('savings.entries.destroy');

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

        // Open to any signed-in account for their own log; ?scope=all is
        // admin-checked in the controller.
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

        // Admin only — enforced in the controller, not just hidden in the UI.
        Route::get('/branding', [SettingsController::class, 'branding'])->name('branding.edit');
        Route::post('/branding', [SettingsController::class, 'updateBranding'])->name('branding.update');

        // Admin only — enforced by UserPolicy in the controller.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        // Own screens: seven fields for the form, and twenty-nine checkboxes for
        // the permissions editor, neither of which fits a phone-sized modal.
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::get('/users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions.edit');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('users.permissions');
        Route::patch('/users/{user}/status', [UserController::class, 'changeStatus'])->name('users.status');
        Route::patch('/users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/colors', [SettingsController::class, 'colors'])->name('colors.edit');
        Route::post('/colors', [SettingsController::class, 'updateColors'])->name('colors.update');

        // Admin only — enforced in the controller. Dashboard spending guidance.
        Route::get('/spending', [SettingsController::class, 'spending'])->name('spending.edit');
        Route::post('/spending', [SettingsController::class, 'updateSpending'])->name('spending.update');

        // Gated on the settings.faq permission in the controller, not just the UI.
        Route::get('/faqs', [FaqController::class, 'index'])->name('faqs.index');
        // Own screens: the answer is a textarea and both fields carry a locale
        // tab strip, which overran a dialog once a validation line appeared.
        Route::get('/faqs/create', [FaqController::class, 'create'])->name('faqs.create');
        Route::get('/faqs/{faq}/edit', [FaqController::class, 'edit'])->name('faqs.edit');
        Route::post('/faqs', [FaqController::class, 'store'])->name('faqs.store');
        Route::post('/faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
        Route::patch('/faqs/{faq}', [FaqController::class, 'update'])->name('faqs.update');
        Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])->name('faqs.destroy');

        // Footer pages (About, Privacy). Gated on settings.pages in the controller.
        Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
        Route::patch('/pages/{page}', [PageController::class, 'update'])->name('pages.update');
    });

    // The reading side of the FAQ. Open to any signed-in user.
    Route::get('/help', [HelpController::class, 'index'])->name('help');

    // Public footer pages, addressed by slug. Drafts 404 in the controller.
    Route::get('/p/{page}', [PageController::class, 'show'])->name('pages.show');
});

require __DIR__.'/auth.php';
