# SpendLog Audit

Findings from a read of the codebase, each one reproduced before being written
down, and each one reproduced again as a failing test before being fixed.

Nothing here is a style opinion. Every item had a concrete failure path, and the
ones marked **reproduced** were demonstrated against the running app rather than
reasoned about.

The three areas the previous round listed as unexamined have now been examined.
They held six more bugs — none of them where the previous round predicted.

---

## Fixed

### 1. A category budget could silently become an overall budget — **reproduced**

**Where:** `app/Http/Requests/BudgetRequest.php`

`budgetAttributes()` validated the category with `exists:categories,uuid`, then
ran a *second* query to turn that uuid into an id. `null` is not a neutral
failure there: it is the schema's encoding for *the overall budget covering
every category*. If the category disappeared between the `exists` check and the
lookup, `value('id')` returned `null` and the row was written as an overall
budget — no exception, no validation error, no log line. "$250 for Food" became
"$250 across everything", and drove the wrong number on the dashboard.

**Fixed:** the lookup now fails loudly with a `ValidationException` rather than
letting the absence mean something else. 422 on the API, redirect-back on the
web — both consumers get the right shape.

- [x] Fix
- [x] Regression test — `BudgetCategoryResolutionTest`. Verified against the old
      code: *"a category budget was silently written as an overall budget"*.

### 2. The API's ability model contradicted the permission model — **reproduced**

**Where:** `app/Enums/TokenAbility.php`

The two halves of the app disagreed about who may create a category, and each
stated its case in a comment: `Permission::forUser()` granted `categories.create`
(*"Read, and add one inline while logging"*) while `TokenAbility::defaults()`
withheld `categories:write` (*"category management is an admin desk job"*).
`grantableTo()` compounded it by gating on `isAdmin()` — a **role** check — while
every policy had moved to **permissions**, so a non-admin could not obtain the
ability even by asking. The inline add-category flow worked in the browser and
was unreachable over the API, for the same user.

**Decided:** abilities are a client scope *derived from* permissions, not a
second policy beside them. A token can never carry an ability its owner's
permissions do not already cover, so the API can only ever be narrower than the
web — never a different answer to the same question.

**Fixed:** `defaults()` and `grantableTo()` both derive from the user's
permissions via a new `TokenAbility::permissions()` map. The `isAdmin()` check is
gone.

- [x] Decide: abilities are a client scope on top of permissions
- [x] Derive `defaults()` / `grantableTo()` from permissions
- [x] Test: a default token can create a category inline
      (`CategoryTest::test_a_default_token_can_create_a_category_inline`)

> Note: the old narrowing — *"a lost phone should not rewrite the taxonomy"* —
> was deliberately dropped. If that protection is wanted back, it belongs as a
> permission (a narrower `forUser()` set), not as a second model that disagrees
> with the first.

### 3. The auth screens showed the wrong brand — **reproduced**

**Where:** `resources/js/Layouts/AuthCardLayout.vue`

Both the lettermark and the wordmark were hardcoded to `S` / `SpendLog` for an
app called MoneyLog, on Forgot Password, Reset Password, Confirm Password and
Verify Email — the screens people hit when they are already confused.

**Fixed:** reads the globally shared `branding` prop, as `AuthenticatedLayout`
does, including the uploaded logo.

- [x] Fix
- [x] Verified in a browser: `/forgot-password` renders the configured name

### 4. `daily_average` divided by a fractional day count — **reproduced**

**Where:** `app/Http/Controllers/ReportController.php`

`$start->diffInDays($last) + 1` assumed the diff truncates to a whole day, which
was Carbon 2's behaviour. Carbon 3 (3.13.1 is installed) returns a **float**, and
the range ends at 23:59:59.999999 — so the diff already spanned the final day and
the `+ 1` billed a day that never elapsed. A fully elapsed week with $700 spent
reported **$87.50/day instead of $100.00**, a 12.5% error, on the report card and
in the PDF export.

**Fixed:** counts whole calendar days.

- [x] Fix
- [x] Regression test — `ReportStatsTest`. Verified against the old code: `87.5`
      where `100.0` belongs.

### 5. The previous-period comparison compared a month to itself — **reproduced**

**Where:** `app/Http/Controllers/ReportController.php`

`$anchor->subMonth()` used Carbon's default day-overflow. Anchored on a day the
previous month lacks, it rolled *forward* into the current month: on 2026-07-31,
`subMonth()` → 2026-07-01, so the "previous period" resolved to July — the period
being reported. The page showed July "vs July 2026" at 0.0% change and silently
discarded the real June comparison. The default landing state hit this, because
with no `at` param the anchor is simply today. Wrong ~6 days a year (Mar 29–31,
May 31, Jul 31, Oct 31, Dec 31); same path feeds the PDF export.

**Fixed:** each anchor is normalised with `startOf…()` before stepping back.

- [x] Fix
- [x] Regression test — `ReportStatsTest`, covering every month-end anchor

### 6. The main nav rendered links the backend then 403'd — **reproduced**

**Where:** `resources/js/Layouts/AuthenticatedLayout.vue`

`links` was a static array rendered with no permission check, in both the desktop
and mobile nav, even though `auth.permissions` is shared and correct. An admin
unticking "View reports" left the user with a visible Reports tab that 403'd on
click. `SettingsLayout.vue` already did this correctly against the same prop —
the main nav simply never consulted it.

**Fixed:** filtered against the shared permission list, mirroring
`SettingsLayout`.

- [x] Fix
- [x] Verified in a browser: revoking `reports.view` hides the tab, and
      `/reports` still 403s — the link is hidden without weakening the gate

### 7. Revoking `dashboard.view` locked the user out of the whole app — **reproduced**

**Where:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

`Permission::DashboardView`'s own description promised *"Without this, signing in
lands on the expenses page."* No such fallback existed — login redirected to
`route('dashboard')` unconditionally, which 403'd. The permission was documented
as a soft preference and behaved as a hard lockout.

**Fixed:** `User::homeRoute()` picks the first page the account may actually
open, walking down to `profile.edit` for an account stripped of every view. All
seven redirect sites and the root doorway use it.

Also closed a hole the obvious fix leaves: `intended()` replays a URL captured
while the user was still a guest, so a bookmarked `/dashboard` landed on a 403
anyway. The stashed URL is now dropped when the user may not open it — and only
then; a bookmark they *can* open still survives login.

- [x] Fix
- [x] Regression test — `Auth\LandingPageTest`, 8 cases including the bookmark
      hole and both directions of `intended()`

### 8. The brand-colour mechanism had never worked — **reproduced**

**Where:** `resources/js/composables/useBrandColors.js`

`useBrandColors` re-applied branding only from a watch deliberately marked *not*
`immediate`, but `AuthenticatedLayout` is remounted on every Inertia visit — so
the watcher was destroyed and rebuilt with a fresh baseline each navigation and
could never fire for a change made between page loads. When an admin changed a
colour, every other logged-in user kept the old brand for their whole session
until a hard refresh. This is exactly what `HandleInertiaRequests` says the
shared props exist to fix: the props arrived, and were never written to the DOM.

Worse than a no-op: the *template* half of branding is reactive while the
*CSS-token* half was not, so a navigation landed in a half-applied state
belonging to neither design — `plain_background` flipped true and stripped the
ambient wash while `--background` stayed stale, giving a bare flat page with no
wash **and** no brand colour.

It only appeared to work on the Colours page, because that page posts with
`preserveState: true` — the one path that keeps the watcher alive.

**Fixed:** the watch is `immediate`. Safe on a full load, where blade has already
written these values: the rules mirror it exactly, so it re-applies what is
already on the element. Idempotent, no frame in between.

- [x] Fix
- [x] Verified in a browser, both halves: `--primary` and `--background` now
      match a hard reload after an Inertia navigation

### 9. The auth screens painted over the admin's background — **reproduced**

**Where:** `resources/js/Layouts/AuthCardLayout.vue`

The shell hardcoded `bg-white ... dark:bg-neutral-950` instead of the
`bg-background` / `text-foreground` tokens, painting straight over the applied
`--background`. With Mint set, every authenticated page went mint and the auth
screens stayed pure white. `lib/appStyles.js` documents this exact trap — *"a
literal white here paints straight over the admin's body colour and the setting
silently does nothing"* — and this was the one shell that never got the memo.

**Fixed:** uses the tokens. The `dark:` overrides are gone; the tokens are
already theme-aware.

- [x] Fix
- [x] Verified in a browser: `/forgot-password` paints `rgb(240, 247, 244)`

---

## Checked and clean

Worth recording so the next audit does not re-tread them.

**From the earlier round:**

- **`CategorySeeder`** — idempotent, matched on the stable English key.
  `whereJsonContains('name->en', ...)` is an exact JSON-path match, so `Food`
  does not collide with `Seafood`.
- **Web controllers no longer leak exception text.** The `withError($e->getMessage())`
  pattern is gone.
- **`ExpenseController` scope guard.** `filter[user]` is only registered while an
  admin is explicitly viewing everyone; the owner scope is applied last so no
  filter can widen it.

**Reports / trends:**

- **`SpendingTrendChart` SVG maths — no division-by-zero, no NaN, in any edge
  case.** Every divisor is guarded (`|| 1`); `areaPath` early-returns below 2
  points; `monotonePath` has explicit `n === 0` and `n === 1` branches. Verified
  by executing the component's computeds against the real `curve.js` for n=0,
  n=1, all-zero, all-identical, all-future. This was the area the previous round
  predicted the bug would be in. It was not.
- **Timezone handling — not a surface here.** `config/app.php` is UTC, there is
  no per-user or per-app timezone setting anywhere, `spent_on` is cast `date` and
  every comparison goes through `toDateString()`. No DST bug is reachable.
- **Date bucketing across boundaries.** `week()`/`month()`/`year()`/`all()`
  iterate `startOf…` → `endOf…` with `addDay()`/`addMonth()`, which cannot
  overflow. The `options()` cursor steps back from an already-normalised value.
- **`resolveAnchor` junk input.** Malformed dates fall back to `now`, future
  anchors are clamped, `tryFrom` on `?period=` falls back to `Month`. A malformed
  query string cannot 500 the page.

**Permissions:**

- **Guard consistency — the thing the previous round flagged as unchecked.**
  Clean, definitively. `Guard::getDefaultName(User::class)` = `web`; all 22
  permission rows and both role rows carry `guard_name = web`. The `sanctum`
  guard has no matching user provider, so it never resolves as a permission
  guard — API requests check against `web` and find their permissions. No
  mismatch exists.
- **Enum ↔ DB parity.** `array_diff` both directions is empty: 22 enum cases, 22
  rows, no permission checked in a policy that isn't seeded, no orphan rows. No
  dead gates.
- **Permission caching.** `RoleSeeder` calls `forgetCachedPermissions()` three
  times; the test env uses `CACHE_STORE=array`.
- **`updatePermissions` input validation.** Validated with
  `Rule::enum(PermissionEnum::class)` — an admin cannot inject a permission
  outside the enum.
- **`UserPolicy::managePermissions` uses `isAdmin()` deliberately, and
  correctly.** Gating it on `users.manage` would let two non-admins grant each
  other full rights.

**Frontend state:**

- **No listener leak on navigation.** The `matchMedia` listener in `useTheme.js`
  is registered at *module* scope, not in a mount hook. Verified by counting:
  still 1 after three navigations and four theme toggles. The `ResizeObserver` is
  torn down in `onBeforeUnmount`; `useNavigating` unsubscribes on unmount.
- **Module-level singleton vs per-component state is right.** `preference` and
  `systemPrefersDark` are module-scoped, which is correct — `ThemeToggle` is
  mounted twice at once and both stay in sync.
- **The two composables do not clobber each other.** The only overlap is
  `applyPalette`, and both feed it from the same source, so order does not
  matter.
- **No flash-of-wrong-theme on first paint.** The blade script is inline in
  `<head>` before the stylesheet, and its rules match `useTheme.readStored()`
  exactly, including junk values.
- **No SSR**, so the module-scope `window`/`localStorage` reads are safe. If SSR
  is ever switched on, `useTheme.js` crashes at import time — a hypothetical, not
  a bug today.
- **Back-button does not replay stale flash toasts.** Inertia does restore the
  stale prop from its history cache, but no toast renders.

---

## Scope — read before trusting the "clean" list

This covers what was **read and reproduced by hand**. It is not a clean bill of
health for the codebase.

The three areas the previous round named as unexamined — reports/trends, the
permission system, and the frontend's cross-cutting state — have now been swept
and are represented above. What remains unexamined:

- **The PDF and Excel exports** beyond the shared `stats()` path fixed here.
  `ExpensesExport` and the DomPDF view were never read.
- **Rate limiting and the auth throttle** past the one login test that touches
  `RateLimiter`.
- **File upload handling** — the logo/favicon path in Branding, which takes user
  files and writes them to public storage.
- **The `expenses.view_all` / `manage_all` admin paths.** The owner-scope guard
  was checked; the admin-override branch it guards was not.

Two patterns worth carrying into the next round, because they produced most of
what was found here:

1. **A comment promising behaviour the code does not deliver.** Three of these
   nine were exactly that — `Permission::DashboardView`'s expenses fallback,
   `HandleInertiaRequests`'s re-apply-on-change, and `appStyles.js` warning about
   the trap `AuthCardLayout` then walked into. In this codebase a confident
   comment is a lead, not a guarantee.
2. **Two individually defensible decisions that only fail in combination.** The
   `immediate: false` and the layout remount were each reasoned about alone and
   were each fine alone.

The suite is green at 572 (10 skipped), up from 535. Every fix above has either a
regression test verified against the broken code, or a browser reproduction
recorded with it.
