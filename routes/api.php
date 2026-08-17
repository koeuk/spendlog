<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FaqAdminController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingsAdminController;
use App\Http\Controllers\Api\V1\UserAdminController;
use App\Http\Controllers\Api\V1\WorkoutController;
use App\Http\Controllers\ReportController as WebReportController;
use Illuminate\Support\Facades\Route;

/*
 * Versioned from day one — retrofitting /v1 once a mobile client is in the wild
 * means supporting both shapes forever.
 *
 * Route keys are UUIDs ({expense:uuid}), never the internal bigint id. The
 * explicit binding is the house convention: it is self-documenting, and it 404s
 * on non-UUID input before touching the database.
 */
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Login is throttled harder than everything else: it is the one endpoint
    // where guessing is the attack.
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('login');

    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:api-login')
        ->name('register');

    // The OTP reset, throttled like login: both are endpoints where guessing
    // is the attack. The per-code limits live in PasswordOtp on top of this.
    Route::post('forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:api-login')
        ->name('password.email');

    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:api-login')
        ->name('password.reset');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', DashboardController::class)
            ->middleware('abilities:'.TokenAbility::DashboardRead->value)
            ->name('dashboard');

        // Reads the same expenses as the dashboard, but over a chosen period
        // and against the one before it. Its own ability, so a client scoped to
        // the home screen does not pick up the whole history with it.
        Route::get('reports', ReportController::class)
            ->middleware('abilities:'.TokenAbility::ReportsRead->value)
            ->name('reports');

        // The same file downloads the web offers, over a bearer token. The web
        // controller only reads $request->user() and gates on viewReports, so
        // it serves both guards unchanged — one export, not two.
        Route::get('reports/export/{format}', [WebReportController::class, 'export'])
            ->middleware('abilities:'.TokenAbility::ReportsRead->value)
            ->name('reports.export');

        // Read and write abilities are checked separately so a token can be
        // read-only without needing a second route table.
        Route::middleware('abilities:'.TokenAbility::ExpensesRead->value)->group(function () {
            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('expenses/{expense:uuid}', [ExpenseController::class, 'show'])->name('expenses.show');
        });

        Route::middleware('abilities:'.TokenAbility::ExpensesWrite->value)->group(function () {
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::patch('expenses/{expense:uuid}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('expenses/{expense:uuid}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        });

        Route::middleware('abilities:'.TokenAbility::CategoriesRead->value)->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/{category:uuid}', [CategoryController::class, 'show'])->name('categories.show');
        });

        // Writes are additionally gated by CategoryPolicy (admin only) — the
        // ability limits the client, the policy limits the user. Both must pass.
        Route::middleware('abilities:'.TokenAbility::CategoriesWrite->value)->group(function () {
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::patch('categories/{category:uuid}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category:uuid}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });

        Route::middleware('abilities:'.TokenAbility::BudgetsRead->value)->group(function () {
            Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
            Route::get('budgets/summary', [BudgetController::class, 'summary'])->name('budgets.summary');
        });

        Route::middleware('abilities:'.TokenAbility::BudgetsWrite->value)->group(function () {
            // Upserts the (category, month) slot, so no separate update route.
            Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
            Route::delete('budgets/{budget:uuid}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
        });

        /*
         * The exercise module.
         *
         * Nothing here needs a special guard for the module being opt-in: the
         * ability is derived from exercise.* permissions (TokenAbility::
         * grantableTo), so an account that was never granted the module cannot
         * hold a token carrying these in the first place. The policies check
         * again behind them.
         */
        Route::middleware('abilities:'.TokenAbility::ExerciseRead->value)->group(function () {
            Route::get('workouts', [WorkoutController::class, 'index'])->name('workouts.index');
            Route::get('workouts/summary', [WorkoutController::class, 'summary'])->name('workouts.summary');
            Route::get('exercises', [WorkoutController::class, 'exercises'])->name('exercises.index');
            // After the literal segments above, or 'summary' binds as a uuid.
            Route::get('workouts/{workout:uuid}', [WorkoutController::class, 'show'])->name('workouts.show');
        });

        Route::middleware('abilities:'.TokenAbility::ExerciseWrite->value)->group(function () {
            Route::post('workouts', [WorkoutController::class, 'store'])->name('workouts.store');
            Route::patch('workouts/{workout:uuid}', [WorkoutController::class, 'update'])->name('workouts.update');
            Route::delete('workouts/{workout:uuid}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
        });

        // The account's own details. Both routes share one ability; the
        // updateProfile / updatePassword gates still rule separately.
        Route::middleware('abilities:'.TokenAbility::ProfileWrite->value)->group(function () {
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('password', [PasswordController::class, 'update'])->name('password.update');
        });

        /*
         * The admin desk. Every route is double-gated: the ability limits the
         * client (an admin can mint a token that cannot touch accounts), and
         * the policy/gate behind it limits the user.
         */
        Route::middleware('abilities:'.TokenAbility::UsersRead->value)->group(function () {
            Route::get('admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
        });

        Route::middleware('abilities:'.TokenAbility::UsersWrite->value)->group(function () {
            Route::post('admin/users', [UserAdminController::class, 'store'])->name('admin.users.store');
            Route::patch('admin/users/{user:uuid}', [UserAdminController::class, 'update'])->name('admin.users.update');
            Route::delete('admin/users/{user:uuid}', [UserAdminController::class, 'destroy'])->name('admin.users.destroy');
        });

        // FAQ reads are open to any token — the Help screen is for everyone.
        Route::get('faqs', [FaqAdminController::class, 'index'])->name('faqs.index');

        Route::middleware('abilities:'.TokenAbility::SettingsWrite->value)->group(function () {
            Route::post('admin/faqs', [FaqAdminController::class, 'store'])->name('admin.faqs.store');
            Route::patch('admin/faqs/{faq:uuid}', [FaqAdminController::class, 'update'])->name('admin.faqs.update');
            Route::delete('admin/faqs/{faq:uuid}', [FaqAdminController::class, 'destroy'])->name('admin.faqs.destroy');

            Route::get('admin/settings/spending', [SettingsAdminController::class, 'spending'])->name('admin.settings.spending');
            Route::put('admin/settings/spending', [SettingsAdminController::class, 'updateSpending'])->name('admin.settings.spending.update');
        });
    });
});
