<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FaqAdminController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RecurringController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SavingsController;
use App\Http\Controllers\Api\V1\SettingsAdminController;
use App\Http\Controllers\Api\V1\UserAdminController;
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

    // The name, marks and colours, readable before sign-in so a client can
    // paint its first screen the way the admin set it.
    Route::get('branding', BrandingController::class)->name('branding');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        // The rate and default currency, for any signed-in client: they are
        // needed to *enter* money, not to administer it.
        Route::get('settings/money', [SettingsAdminController::class, 'money'])->name('settings.money');
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

        // Income, beside expenses: the same owner scoping, the same read/write
        // split. The literal 'summary' segment is registered before the uuid
        // route, or it binds as a uuid and 404s.
        Route::middleware('abilities:'.TokenAbility::IncomesRead->value)->group(function () {
            Route::get('incomes', [IncomeController::class, 'index'])->name('incomes.index');
            Route::get('incomes/summary', [IncomeController::class, 'summary'])->name('incomes.summary');
            Route::get('incomes/sources', [IncomeController::class, 'sources'])->name('incomes.sources');
            Route::get('incomes/{income:uuid}', [IncomeController::class, 'show'])->name('incomes.show');
        });

        Route::middleware('abilities:'.TokenAbility::IncomesWrite->value)->group(function () {
            Route::post('incomes', [IncomeController::class, 'store'])->name('incomes.store');
            Route::patch('incomes/{income:uuid}', [IncomeController::class, 'update'])->name('incomes.update');
            Route::delete('incomes/{income:uuid}', [IncomeController::class, 'destroy'])->name('incomes.destroy');
        });

        /*
         * Recurring rules: templates that write expense and income rows on a
         * schedule. They deliberately carry no ability of their own.
         *
         * A rule is not a third kind of money — it is a *deferred* expense or
         * income, and it can create nothing its holder could not create by
         * hand a moment later. So the scope that already covers those rows
         * covers scheduling them, and RecurringRulePolicy still rules per rule
         * on the row kind's permissions.
         *
         * 'ability' (any), not 'abilities' (all): someone scoped to income
         * alone must still be able to schedule a salary. It is also what keeps
         * tokens minted before this feature working — a new ability string
         * would have 403'd every one of them until the holder signed in again.
         */
        Route::middleware('ability:'.TokenAbility::ExpensesRead->value.','.TokenAbility::IncomesRead->value)->group(function () {
            Route::get('recurring', [RecurringController::class, 'index'])->name('recurring.index');
            Route::get('recurring/{rule:uuid}', [RecurringController::class, 'show'])->name('recurring.show');
        });

        Route::middleware('ability:'.TokenAbility::ExpensesWrite->value.','.TokenAbility::IncomesWrite->value)->group(function () {
            Route::post('recurring', [RecurringController::class, 'store'])->name('recurring.store');
            Route::patch('recurring/{rule:uuid}', [RecurringController::class, 'update'])->name('recurring.update');
            Route::delete('recurring/{rule:uuid}', [RecurringController::class, 'destroy'])->name('recurring.destroy');
        });

        Route::middleware('abilities:'.TokenAbility::SavingsRead->value)->group(function () {
            // Literal segments first: 'summary', 'plan' and 'entries' would
            // otherwise bind as a uuid to whatever came before them.
            Route::get('savings/summary', [SavingsController::class, 'summary'])->name('savings.summary');
            Route::get('savings/plan', [SavingsController::class, 'plan'])->name('savings.plan');
            // The month's ledger, not the plans: savings is read a month at a
            // time, like budgets.
            Route::get('savings', [SavingsController::class, 'index'])->name('savings.index');
        });

        Route::middleware('abilities:'.TokenAbility::SavingsWrite->value)->group(function () {
            // Upserts the (user, month) slot, like POST /budgets — there is no
            // separate update route.
            Route::post('savings/plan', [SavingsController::class, 'storePlan'])->name('savings.plan.store');
            Route::delete('savings/plan/{plan:uuid}', [SavingsController::class, 'destroyPlan'])
                ->name('savings.plan.destroy');

            // The ledger. Entries stand on their own now that goals are gone,
            // and are authorised by SavingsEntryPolicy.
            Route::post('savings/entries', [SavingsController::class, 'storeEntry'])->name('savings.entries.store');
            Route::patch('savings/entries/{entry:uuid}', [SavingsController::class, 'updateEntry'])
                ->name('savings.entries.update');
            Route::delete('savings/entries/{entry:uuid}', [SavingsController::class, 'destroyEntry'])
                ->name('savings.entries.destroy');
        });

        // Your own activity log needs no ability: it is a record of what this
        // account did, not a way to do more. ?scope=all is admin-checked inside.
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');

        // The profile photo is open to every signed-in account — no ability,
        // no gate. It is cosmetic: unlike the name or email it cannot be used
        // to impersonate or lock anyone out, so a narrow token may still set it.
        Route::post('profile/avatar', [ProfileController::class, 'storeAvatar'])->name('profile.avatar.store');
        Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

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
            // An admin setting someone's photo for them; UserPolicy::update rules.
            Route::post('admin/users/{user:uuid}/avatar', [UserAdminController::class, 'storeAvatar'])->name('admin.users.avatar.store');
            Route::delete('admin/users/{user:uuid}/avatar', [UserAdminController::class, 'destroyAvatar'])->name('admin.users.avatar.destroy');
        });

        // FAQ reads are open to any token — the Help screen is for everyone.
        Route::get('faqs', [FaqAdminController::class, 'index'])->name('faqs.index');

        Route::middleware('abilities:'.TokenAbility::SettingsWrite->value)->group(function () {
            Route::post('admin/faqs', [FaqAdminController::class, 'store'])->name('admin.faqs.store');
            Route::patch('admin/faqs/{faq:uuid}', [FaqAdminController::class, 'update'])->name('admin.faqs.update');
            Route::delete('admin/faqs/{faq:uuid}', [FaqAdminController::class, 'destroy'])->name('admin.faqs.destroy');

            Route::get('admin/settings/spending', [SettingsAdminController::class, 'spending'])->name('admin.settings.spending');
            Route::put('admin/settings/spending', [SettingsAdminController::class, 'updateSpending'])->name('admin.settings.spending.update');

            // POST, not PUT: multipart bodies only parse on POST in PHP.
            Route::get('admin/settings/branding', [SettingsAdminController::class, 'branding'])->name('admin.settings.branding');
            Route::post('admin/settings/branding', [SettingsAdminController::class, 'updateBranding'])->name('admin.settings.branding.update');

            Route::get('admin/settings/colors', [SettingsAdminController::class, 'colors'])->name('admin.settings.colors');
            Route::put('admin/settings/colors', [SettingsAdminController::class, 'updateColors'])->name('admin.settings.colors.update');
        });
    });
});
