# SpendLog Audit — open bugs

Findings from a read of the codebase, each one reproduced before being written
down. Ordered by severity: silent data corruption first, then contradictions
between two halves of the app, then cosmetic.

Nothing here is a style opinion or a missing test. Every item has a concrete
failure path, and the ones marked **reproduced** were demonstrated against the
running app rather than reasoned about.

---

## 1. A category budget can silently become an overall budget — **reproduced**

**Where:** `app/Http/Requests/BudgetRequest.php:43-58`

`budgetAttributes()` validates the category with `exists:categories,uuid`, then
runs a *second* query to turn that uuid into an id:

```php
'category_id' => $categoryUuid
    ? Category::where('uuid', $categoryUuid)->value('id')
    : null,
```

**Why it bites:** `null` is not a neutral failure here. It is the schema's
encoding for *the overall budget covering every category* — stated in this same
file (line 22), in `BudgetSummary.php:55`, and enforced by the `category_key`
generated column. So if the category disappears between the `exists` check and
the lookup, `value('id')` returns `null`, and the row is written as an overall
budget. No exception, no validation error, no log line.

**Failure scenario:** an admin deletes a category in the moment between a user's
validation pass and the write. The user set "$250 for Food" and now has "$250
across everything" — which then drives the wrong number on the dashboard and the
budget bar, with nothing to indicate it happened.

**Reproduced:**

```
Category::where(uuid)->value(id) => NULL
category_id written to the budget => NULL   ← "overall budget"
```

**Fix:** resolve the model once and fail loudly if it vanished, rather than
letting the absence mean something else:

```php
$category = $categoryUuid ? Category::where('uuid', $categoryUuid)->first() : null;

if ($categoryUuid && ! $category) {
    // the row went away between validation and here — this is not an overall budget
}
```

That also removes the second round trip.

- [ ] Fix
- [ ] Regression test: a category budget whose uuid stops resolving must not be written as overall

---

## 2. The API's ability model contradicts the permission model — **reproduced**

**Where:** `app/Enums/TokenAbility.php:25-55` vs `app/Enums/Permission.php:166-190`

The two halves of the app disagree about who may create a category, and each
states its case in a comment:

| | says |
|---|---|
| `Permission::forUser()` | *"Read, and add one inline while logging."* — grants `categories.create` |
| `TokenAbility::defaults()` | *"category management is an admin desk job"* — withholds `categories:write` |

`grantableTo()` compounds it by gating on `isAdmin()` — a **role** check — while
every policy in the app has moved to **permissions**. So a non-admin cannot
obtain the ability even by asking for it at login.

**Failure scenario:** a mobile client logs in as a normal user and tries the
inline "add a category while logging an expense" flow the web grants them. The
ability middleware returns 403 before the policy — which would have allowed it —
is ever consulted. The feature works in the browser and is unreachable over the
API, for the same user.

**Reproduced:**

```
user role, category permissions on the WEB:
  ✓ categories.view
  ✓ categories.create

same user, category abilities on a default API TOKEN:
  ✓ categories:read
  ✗ categories:write
```

**Fix — decide which model wins, then make one follow the other.** The abilities
were written before the permission system existed and were never revisited. The
smallest coherent change is to derive the default abilities *from* the user's
permissions rather than from a hardcoded list plus an `isAdmin()` check.

- [ ] Decide: are abilities a client-scope on top of permissions, or a second policy?
- [ ] Derive `defaults()` / `grantableTo()` from permissions
- [ ] Test: a user with `categories.create` can create one over the API

---

## 3. The auth screens show the wrong brand — **reproduced**

**Where:** `resources/js/Layouts/AuthCardLayout.vue:29-32`

```html
<span class="bg-primary ...">S</span>
SpendLog
```

Both the lettermark and the wordmark are hardcoded. The app is called
**MoneyLog**, and `AuthenticatedLayout` computes its initial from
`branding.name` and swaps in the uploaded logo — this layout does neither.

**Failure scenario:** Forgot Password, Reset Password, Confirm Password and
Verify Email all render a lettermark `S` and the wordmark "SpendLog" for an app
called MoneyLog. Anyone who uploads a logo sees it everywhere except the screens
they hit when they are already confused.

**Reproduced:** `/forgot-password` returns `{'bg': 'rgb(180, 39, 39)', 'letter': 'S'}`
— the brand *colour* applies, the brand *name* does not.

**Fix:** read `usePage().props.branding` here as the authenticated layout does —
the prop is shared globally, so it is already available on these pages.

- [ ] Fix
- [ ] Test: the auth screens render the configured app name

---

## Checked and clean

Worth recording so the next audit does not re-tread them:

- **`CategorySeeder`** — idempotent, matched on the stable English key.
  `whereJsonContains('name->en', ...)` is an exact JSON-path match, so `Food`
  does not collide with `Seafood`.
- **Web controllers no longer leak exception text.** The `withError($e->getMessage())`
  pattern — which surfaced raw `SQLSTATE` strings, table names and index names to
  users — is gone.
- **`ExpenseController` scope guard.** `filter[user]` is only registered while an
  admin is explicitly viewing everyone, so a non-admin cannot use it to probe for
  other people's rows; the owner scope is applied last so no filter can widen it.

---

## Scope of this audit — read before trusting the "clean" list

This covers what was **read and reproduced by hand**. It is not a clean bill of
health for the codebase.

A broader sweep of security/authorization, data integrity and the frontend was
started and stopped before it reported, so **those areas are unexamined**, not
cleared. Three areas in particular have never been audited and are where the
next real bug most likely lives:

- **Reports / trends** (`ReportController`, the chart components) — date
  bucketing across week/month/year boundaries, timezone handling, and
  comparison-to-previous-period. `SpendingTrendChart` does its own SVG maths, so
  empty data and division-by-zero are worth a look.
- **The permission system**, which is new and now backs every policy. Whether the
  `web` guard, the seeded role permissions and `HandleInertiaRequests`'s shared
  `permissions` list all agree has not been checked.
- **The frontend's cross-cutting state** — `useTheme` and `useBrandColors` both
  write CSS custom properties to `document.documentElement`, and the layout
  remounts on every Inertia visit. That combination has already produced two bugs
  this session.

The test suite is green at 535 (10 skipped), so none of the three findings above
is currently covered — each needs the regression test listed with it.
