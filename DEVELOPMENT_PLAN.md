# SpendLog Development Plan

## Phase 1: Foundation (Setup + Auth) ✅
- [✅] Create Laravel project (used `composer create-project` — Laravel 13)
- [✅] Install Breeze with Vue + Inertia: `php artisan breeze:install vue`
- [✅] Set up shadcn-vue: `npx shadcn-vue@latest init`
- [✅] ~~Add role column to users migration~~ → replaced with **spatie/laravel-permission**
- [✅] Run migrations, create a test admin user via seeder
- [✅] Confirm login/register/logout works end-to-end (verified over HTTP)

## Phase 2: Database & Models ✅
- [✅] Create migrations: categories, expenses, budgets
- [✅] Create models: Category, Expense, Budget with relationships
- [✅] Add fillable, casts (price → decimal, month/spent_on → date)
- [✅] Seed a few default categories (Food, Transport, Bills, Shopping, Other)

## Phase 3: Categories CRUD (Admin-managed) ✅
- [✅] Create CategoryController — index, store, update, destroy
- [✅] Implement Policy: only admin can create/edit/delete (verified: non-admin → 403)
- [✅] Build simple page: Pages/Categories/Index.vue — list + create/edit modal (Dialog, Input, Button)
- [✅] Category color + icon picker in the modal (10 colours, 16 lucide icons; verified rendering)

## Phase 4: Expenses CRUD (Core Feature) ✅
- [✅] Create ExpenseController — index (grouped by date), store, update, destroy
- [✅] Implement route model binding + ownership check (verified: cross-user edit/delete → 403, admin allowed)
- [✅] Build Pages/Expenses/Index.vue — daily-grouped list
- [✅] Create ExpenseForm.vue component — item, price, category dropdown, date picker (Select, Popover+Calendar)
- [✅] Implement quick-add flow: modal, not a separate page
- [✅] Show category color/icon on expense rows + in the category dropdown

## Phase 5: Budgets ✅
- [✅] Create BudgetController — set/update monthly budget (overall or per category), upsert on (user, category, month)
- [✅] Build Pages/Budgets/Index.vue — form to set budget per category per month, with month navigation
- [✅] Calculate "spent vs budget" per category (BudgetProgress bar; verified 106% → red/over)

## Phase 6: Dashboard (The Payoff Screen) ✅
- [✅] Display today's total, this month's total (+ left to spend)
- [✅] Show spending by category — styled bars in each category's own colour, no chart lib
- [✅] Add budget progress bars (green/amber/red; verified 96% → amber, 106% → red)
- [✅] Include recent expenses list (last 8)

## Phase 7: Admin Extras ✅
- [✅] Enable admin to view all users' expenses (`?scope=all` + filter by user; verified a non-admin
      cannot escalate — scope is ignored and `filter[user]` 400s)
- [✅] Admin manages categories (already covered in Phase 3)
- [✅] Add simple admin toggle/badge in the UI (Mine/Everyone toggle + ADMIN badge in the nav)

## Phase 8: Polish ✅
- [✅] Add empty states ("No expenses yet, add your first one") — Expenses, Categories, Dashboard
- [✅] Implement loading skeletons (GET visits only, via useNavigating — Expenses list + Budgets)
- [✅] Add toast notifications on create/update/delete (Sonner, fed by the shared flash props)
- [✅] Mobile-responsive check at 390px — no horizontal overflow on any page; quick-add modal verified

## Phase 9: API Layer (Future Integration)

Goal: expose the same features over JSON so a mobile app or third-party client can use them,
without duplicating the business logic that already backs the Inertia pages.

### 9.1 Prepare the shared core — **deferred, deliberately**
- [ ] Extract business logic out of the web controllers into Actions/Services
      (e.g. `App\Actions\Expenses\StoreExpense`) so web + API call the same code
- [✅] Keep validation in Form Requests — the API reuses `ExpenseRequest`,
      `CategoryRequest` and `BudgetRequest` unchanged, including their
      `expenseAttributes()` / `budgetAttributes()` uuid→id translation
- [✅] Move "spent vs budget" math into a service — `App\Services\BudgetSummary`
      is genuinely shared by the web Dashboard, the Budgets page and the API

> **Known debt.** The extraction was skipped to keep the working web controllers
> untouched, so create/update/delete logic now exists in both stacks. This is the
> drift the design note below warns about — the API is correct today, but a rule
> changed in one stack will not follow into the other. See "Known debt" at the end.

### 9.2 Scaffold ✅
- [✅] Ran `php artisan install:api` — `routes/api.php` created, wired in `bootstrap/app.php`
- [✅] Added `HasApiTokens` to the User model
- [✅] Controllers under `App\Http\Controllers\Api\V1`, routes prefixed `/api/v1` and named `api.v1.*`
- [✅] Aliased Sanctum's `abilities`/`ability` middleware — Laravel 11+ registers
      neither, so `abilities:` on a route silently resolves to nothing without it

### 9.3 Auth ✅
- [✅] `POST /api/v1/login` — issues a token; a wrong password and an unknown email
      return the identical 422, so the endpoint is not a user-enumeration oracle
- [✅] `POST /api/v1/logout` — revokes **only the calling token**, so one device signing out
      doesn't sign the others out
- [✅] `POST /api/v1/register` — mirrors the web flow incl. the verification email;
      always assigns the `user` role, never from request input
- [✅] `GET /api/v1/me` — current user + `is_admin`
- [✅] Everything else behind `auth:sanctum`
- [✅] Token abilities (`App\Enums\TokenAbility`): new tokens get everything **except**
      `categories:write`. A client may request a narrower token; the request is
      intersected with what the user may grant, so it can never widen.
      Abilities gate the *client*, policies gate the *user*, and both must pass.

### 9.4 Resources ✅
- [✅] `ExpenseResource`, `CategoryResource`, `BudgetResource`, `UserResource`
- [✅] Never returns raw models
- [✅] **Money is a string** (`"12.50"`) — decided, and documented in `docs/API.md`.
      `BudgetSummaryResource` normalises the shared service's floats at the API
      boundary so the summary/dashboard don't answer `10` where the rest answer `"10.00"`
- [✅] Dates: ISO 8601 UTC timestamps, `YYYY-MM-DD` days, `YYYY-MM` months

### 9.5 Endpoints ✅
- [✅] `GET/POST/PATCH/DELETE /api/v1/expenses` — paginated, filter by item/category/date
      range, sort, `per_page` clamped to 100; admin `?scope=all`
- [✅] `GET /api/v1/categories` + admin-only writes; `DELETE` returns **409** (not 500)
      when expenses/budgets still reference the category
- [✅] `GET/POST/DELETE /api/v1/budgets` — `POST` upserts the (category, month) slot,
      so it's idempotent and needs no separate update route; `GET /budgets/summary`
- [✅] `GET /api/v1/dashboard` — one call for the whole home screen
- [✅] Reuses the same Policies as the web side — ownership is not re-implemented

### 9.6 Hardening ✅
- [✅] Rate limiting: 60/min per token; login+register 5/min per email+IP **and** 20/min
      per IP — keyed by both so an attacker can't lock a victim out by failing on purpose
- [✅] CORS via `config/cors.php`, origins from `CORS_ALLOWED_ORIGINS`
- [✅] Consistent JSON errors — `shouldRenderJsonWhen(api/*)` was already in place
- [✅] **65 feature tests** across auth/expenses/categories/budgets/dashboard: happy path,
      unauthenticated, wrong-owner, admin override, ability gating, enumeration oracle,
      mass-assignment, upsert idempotency. Factories added for Category/Expense/Budget.

### 9.7 Docs ✅
- [✅] [Scribe](https://scribe.knuckles.wtf) at `/docs` (+ `/docs.openapi`, `/docs.postman`),
      generated from controller annotations. Guarded by `RestrictDocsAccess`:
      open in `local`, admin-only elsewhere — Scribe adds the route with no
      middleware by default, and Try It Out fires real requests
- [✅] Example request/response per endpoint, via `@response` annotations
- [✅] `docs/API.md` for the cross-cutting conventions a per-endpoint page can't carry

### Design notes
- Web (Inertia) and API are two *transports* over one core. If logic lives in the controller,
  the API phase becomes a copy-paste job that drifts. That's why 9.1 comes first.
- Sanctum has two modes: SPA cookie auth (same domain) and personal access tokens (mobile/3rd party).
  This plan assumes **tokens** — the Inertia frontend keeps using normal session auth.

### Known debt (from this phase)
- **9.1 not done.** Create/update/delete logic is written out in both the web and API
  controllers. `BudgetSummary` is shared; nothing else is. Extracting Actions is the
  fix, and it gets more expensive the longer both stacks evolve.
- **`BudgetRequest::budgetAttributes()` double-queries.** It validates
  `exists:categories,uuid` and then re-queries for the id. Besides the extra round
  trip, a category deleted between the two turns a category budget into an *overall*
  budget silently, because `null` category_id means "overall".
- **Web error handling leaks exception text.** The web controllers flash
  `$e->getMessage()`, which for a `QueryException` is raw SQL with table and index
  names, and they no longer `report($e)` — so users see everything and the logs see
  nothing. The API does not have this problem. (Left alone deliberately: API-only scope.)
- **`.env` has `LOG_DEPRECATIONS_CHANNEL=null`**, which Dotenv turns into PHP `null`,
  making Laravel fall back to the *default* channel instead of discarding. Quote it
  (`="null"`) to get the intended behaviour.

### Design notes
- Web (Inertia) and API are two *transports* over one core. If logic lives in the controller,
  the API phase becomes a copy-paste job that drifts. That's why 9.1 comes first.
- Sanctum has two modes: SPA cookie auth (same domain) and personal access tokens (mobile/3rd party).
  This plan assumes **tokens** — the Inertia frontend keeps using normal session auth.

## Tech Stack
- **Backend**: Laravel 13 with Breeze authentication
- **Roles**: spatie/laravel-permission (`admin` / `user`)
- **Frontend**: Vue 3 + Inertia.js
- **UI Components**: shadcn-vue (reka-ui, "vega" style, lucide icons)
- **Database**: MySQL 8
- **Styling**: Tailwind CSS **v4** (upgraded — Breeze installs v3, but shadcn-vue's components require v4)
- **API** (Phase 9): Laravel Sanctum token auth + API Resources, versioned at `/api/v1`

## Key Decisions
- **IDs**: every table has a `bigint` `id` primary key (compact FKs/joins) **plus** an indexed
  `uuid` used as the public route key. `id` is hidden from JSON, so only UUIDs reach the
  frontend. Shared via the `HasUuidRouteKey` trait.
- **Budgets**: `category_id` nullable — null means an overall budget. A `category_key` stored
  generated column (`COALESCE(category_id, 0)`) backs the unique index, because MySQL treats
  NULLs as distinct and would otherwise allow duplicate overall budgets.
- **Category deletion**: restricted (not cascading) while expenses/budgets reference it; the UI
  shows a friendly error instead of a 500.
- **Mass assignment**: `user_id` is never fillable — expenses are created through the
  `$user->expenses()` relationship. Roles are assigned explicitly, never from request input.

## Key Features
- Daily expense tracking with quick-add functionality
- Category-based organization
- Monthly budget management
- Admin role for category management and user oversight
- Mobile-friendly interface for on-the-go logging
- Dashboard with spending insights and budget progress
