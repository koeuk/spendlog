<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\WorkoutController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', DashboardController::class)
            ->middleware('abilities:'.TokenAbility::DashboardRead->value)
            ->name('dashboard');

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
    });
});
