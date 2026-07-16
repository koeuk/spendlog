# SpendLog Development Plan

## Phase 1: Foundation (Setup + Auth)
- [ ] Create Laravel project: `laravel new spendlog`
- [ ] Install Breeze with Vue + Inertia: `php artisan breeze:install vue`
- [ ] Set up shadcn-vue: `npx shadcn-vue@latest init`
- [ ] Add role column to users migration
- [ ] Run migrations, create a test admin user via seeder
- [ ] Confirm login/register/logout works end-to-end

## Phase 2: Database & Models
- [ ] Create migrations: categories, expenses, budgets
- [ ] Create models: Category, Expense, Budget with relationships
- [ ] Add fillable, casts (price → decimal, month/spent_on → date)
- [ ] Seed a few default categories (Food, Transport, Bills, Shopping, Other)

## Phase 3: Categories CRUD (Admin-managed)
- [ ] Create CategoryController — index, store, update, destroy
- [ ] Implement Policy or manual check: only admin can create/edit/delete categories
- [ ] Build simple page: Pages/Categories/Index.vue — list + create/edit modal (shadcn-vue Dialog, Input, Button)

## Phase 4: Expenses CRUD (Core Feature)
- [ ] Create ExpenseController — index (grouped by date), store, update, destroy
- [ ] Implement route model binding + ownership check (user_id === auth()->id() unless admin)
- [ ] Build Pages/Expenses/Index.vue — daily-grouped list
- [ ] Create ExpenseForm.vue component — item, price, category dropdown, date picker (shadcn-vue Select, Popover+Calendar)
- [ ] Implement quick-add flow: modal or inline form, not a separate page (faster for daily logging)

## Phase 5: Budgets
- [ ] Create BudgetController — set/update monthly budget (overall or per category)
- [ ] Build Pages/Budgets/Index.vue — form to set budget per category per month
- [ ] Calculate "spent vs budget" per category (progress bar component)

## Phase 6: Dashboard (The Payoff Screen)
- [ ] Display today's total, this month's total
- [ ] Show spending by category (simple bar or donut — Chart.js or just styled bars, no need for a heavy lib)
- [ ] Add budget progress bars (green/yellow/red based on % used)
- [ ] Include recent expenses list (last 5-10)

## Phase 7: Admin Extras
- [ ] Enable admin to view all users' expenses (filter by user)
- [ ] Admin manages categories (already covered in Phase 3)
- [ ] Add simple admin toggle/badge in the UI to distinguish views

## Phase 8: Polish
- [ ] Add empty states ("No expenses yet, add your first one")
- [ ] Implement loading skeletons
- [ ] Add toast notifications on create/update/delete (shadcn-vue Sonner/Toast)
- [ ] Mobile-responsive check (quick-add should work well on phone since that's likely how you'll log daily spending)

## Phase 9: API Layer (Future Integration)

Goal: expose the same features over JSON so a mobile app or third-party client can use them,
without duplicating the business logic that already backs the Inertia pages.

### 9.1 Prepare the shared core (do this before writing any API controller)
- [ ] Extract business logic out of the web controllers into Actions/Services
      (e.g. `App\Actions\Expenses\StoreExpense`) so web + API call the same code
- [ ] Keep validation in Form Requests — reuse the same request classes for both stacks
- [ ] Move "spent vs budget" math out of the controller into a service (Dashboard needs it, API will too)

### 9.2 Scaffold
- [ ] Run `php artisan install:api` — creates `routes/api.php` and wires Sanctum
      (Laravel 11+ does not ship this file by default; Sanctum is already a dependency via Breeze)
- [ ] Add `HasApiTokens` trait to the User model
- [ ] Namespace controllers under `App\Http\Controllers\Api\V1` and prefix routes `/api/v1`
      — versioning from day one is much cheaper than retrofitting it

### 9.3 Auth
- [ ] `POST /api/v1/login` — issue a Sanctum personal access token, return it
- [ ] `POST /api/v1/logout` — revoke the current token
- [ ] `POST /api/v1/register` — optional, mirrors Breeze's register
- [ ] `GET  /api/v1/me` — current user + role
- [ ] Protect everything else with `auth:sanctum`
- [ ] Decide token scopes/abilities if the mobile client should be limited (e.g. no category writes)

### 9.4 Resources (response shaping)
- [ ] Create API Resources: `ExpenseResource`, `CategoryResource`, `BudgetResource`, `UserResource`
- [ ] Never return raw models — resources keep the payload stable when columns change
- [ ] Cast money consistently (decide: string vs float vs integer cents — pick one and document it)
- [ ] Return dates in ISO 8601 / UTC and let the client localize

### 9.5 Endpoints
- [ ] `GET/POST/PATCH/DELETE /api/v1/expenses` — paginated index, filter by date range + category
- [ ] `GET /api/v1/categories` — read for all, write restricted to admin
- [ ] `GET/POST/PATCH /api/v1/budgets` — per month, per category
- [ ] `GET /api/v1/dashboard` — today total, month total, by-category breakdown, budget progress
      (one call, so a mobile home screen isn't 4 round trips)
- [ ] Reuse the same Policies as the web side — ownership check must not be re-implemented here

### 9.6 Hardening
- [ ] Apply rate limiting (throttle login harder than the rest)
- [ ] Configure CORS in `config/cors.php` for the client origin
- [ ] Return consistent JSON errors (422 validation, 401 unauth, 403 forbidden, 404 missing)
- [ ] Feature tests per endpoint: happy path, unauthenticated, wrong-owner, admin override

### 9.7 Docs
- [ ] Document endpoints (Scramble or Laravel API Documentation Generator — auto-generated from code beats a stale markdown table)
- [ ] Keep an example request/response per endpoint for the client developer

### Design notes
- Web (Inertia) and API are two *transports* over one core. If logic lives in the controller,
  the API phase becomes a copy-paste job that drifts. That's why 9.1 comes first.
- Sanctum has two modes: SPA cookie auth (same domain) and personal access tokens (mobile/3rd party).
  This plan assumes **tokens** — the Inertia frontend keeps using normal session auth.

## Tech Stack
- **Backend**: Laravel with Breeze authentication
- **Frontend**: Vue 3 + Inertia.js
- **UI Components**: shadcn-vue
- **Database**: MySQL/PostgreSQL
- **Styling**: Tailwind CSS (comes with Breeze)
- **API** (Phase 9): Laravel Sanctum token auth + API Resources, versioned at `/api/v1`

## Key Features
- Daily expense tracking with quick-add functionality
- Category-based organization
- Monthly budget management
- Admin role for category management and user oversight
- Mobile-friendly interface for on-the-go logging
- Dashboard with spending insights and budget progress