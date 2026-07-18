<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Exercise\DashboardController as ExerciseDashboardController;
use App\Http\Controllers\Exercise\ExerciseTypeController;
use App\Http\Controllers\Exercise\WorkoutController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
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
    Route::resource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('expenses', ExpenseController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->name('reports.export');

    // store() upserts the (category, month) slot, so no separate update route.
    Route::resource('budgets', BudgetController::class)
        ->only(['index', 'store', 'destroy']);

    /*
     * The exercise module.
     *
     * Namespaced under an `exercise.` prefix, unlike the flat finance route
     * names — the workspace switcher in AuthenticatedLayout decides which
     * module you are in by matching `exercise.*`, so the prefix is load-bearing
     * rather than cosmetic.
     *
     * Every route authorizes through WorkoutPolicy / ExerciseTypePolicy in its
     * controller, both of which require exercise.view. The module ships locked
     * (see Permission::forUser), so this is invisible until an admin grants it.
     */
    Route::prefix('exercise')->name('exercise.')->group(function () {
        Route::get('/', [ExerciseDashboardController::class, 'index'])->name('dashboard');

        Route::resource('workouts', WorkoutController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        /*
         * "Movements" in the UI, exercise_types in the code. The parameter is
         * named explicitly because Laravel would otherwise bind {exercise_type}
         * from the resource name and ExerciseTypeRequest reads the route
         * parameter by that name when checking uniqueness on update.
         */
        Route::resource('types', ExerciseTypeController::class)
            ->parameters(['types' => 'exercise_type'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

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

        // Admin only — enforced by UserPolicy in the controller.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('users.permissions');
        Route::patch('/users/{user}/status', [UserController::class, 'changeStatus'])->name('users.status');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/colors', [SettingsController::class, 'colors'])->name('colors.edit');
        Route::post('/colors', [SettingsController::class, 'updateColors'])->name('colors.update');

        // Admin only — enforced in the controller. Dashboard spending guidance.
        Route::get('/spending', [SettingsController::class, 'spending'])->name('spending.edit');
        Route::post('/spending', [SettingsController::class, 'updateSpending'])->name('spending.update');

        /*
         * Exercise preferences. Gated on exercise.view in the controller, not
         * the admin check the pages around it use — the module is granted per
         * person, so a non-admin who holds it still needs to set their unit.
         */
        Route::get('/exercise', [SettingsController::class, 'exercise'])->name('exercise-settings.edit');
        Route::post('/exercise', [SettingsController::class, 'updateExercise'])->name('exercise-settings.update');

        // Gated on the settings.faq permission in the controller, not just the UI.
        Route::get('/faqs', [FaqController::class, 'index'])->name('faqs.index');
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
