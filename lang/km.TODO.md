# Khmer translation worklist

215 strings reach the UI in English while the locale is `km`.
They are now keys in `lang/en.json` and absent from `lang/km.json`, so the
outstanding set is always `array_diff_key(en, km)` — this file is a snapshot.

Add each to `lang/km.json`. Placeholders (`:name`, `:count`) must survive
verbatim; `&quot;` is an HTML-escaped quote in a Vue confirm dialog.

## Backend: app/Enums/Permission.php (63)

- `Add categories`
- `Add expenses`
- `Add savings goals`
- `Adds the “Everyone” view on the expenses page.`
- `Administration`
- `Categories are shared, so this changes them for everyone.`
- `Change branding and colours`
- `Change budgets`
- `Change one already set.`
- `Change own name and email`
- `Change own password`
- `Clear a budget.`
- `Create, edit and suspend users`
- `Delete a goal and everything saved against it.`
- `Delete categories`
- `Delete own expenses`
- `Delete own income`
- `Delete own savings goals`
- `Edit a goal, deposit into it, withdraw from it.`
- `Edit and delete any expense`
- `Edit and delete anyone’s income`
- `Edit categories`
- `Edit own expenses`
- `Edit own income`
- `Edit own savings goals`
- `Edit the footer pages (About, Privacy)`
- `Includes assigning roles. Granting permissions stays admin-only.`
- `Includes naming one inline from the expense dialog.`
- `Manage anyone’s budgets`
- `Manage anyone’s savings goals`
- `Manage the help / FAQ entries`
- `Needed to file an expense against one.`
- `Not just their own.`
- `Only categories with no expenses can be deleted.`
- `Pages`
- `Record a salary, a sale, a gift.`
- `Remove budgets`
- `Revoke this where names and emails come from elsewhere.`
- `Revoke this where passwords are managed centrally.`
- `See the user list in settings.`
- `Set a budget where none exists yet.`
- `Set budgets`
- `Start a goal.`
- `The About and Privacy pages linked in the footer.`
- `The app name, logo, favicon and colours.`
- `The quick-add form.`
- `Their own budgets page.`
- `Their own goals and what is put aside for each.`
- `Their own income log.`
- `Their own log. Revoking this hides the page.`
- `Their own only, unless granted the one below.`
- `Trends and comparisons over time.`
- `View categories`
- `View everyone’s expenses`
- `View own budgets`
- `View own expenses`
- `View own income`
- `View own savings goals`
- `View reports`
- `View the dashboard`
- `View users`
- `Without this, signing in lands on the expenses page.`
- `Write and order the questions on the help page.`

## Backend: app/Enums/RoleName.php (2)

- `Super admin`
- `User`

## Backend: app/Enums/UserStatus.php (11)

- `Active`
- `Archived`
- `Can sign in and use the app normally.`
- `Cannot sign in yet. Use this for an account that has been set up but not handed over.`
- `Cannot sign in, and the account is treated as closed. Their expenses are kept.`
- `Cannot sign in. Any open session ends on their next click, and API tokens are revoked.`
- `Invited`
- `Suspended`
- `This account has been closed. Please contact an administrator.`
- `Your account has been suspended. Please contact an administrator.`
- `Your account is not active yet. Please contact an administrator.`

## Backend: app/Http/Controllers/Api/V1/ActivityController.php (1)

- `Unknown activity subject: :kinds.`

## Backend: app/Http/Controllers/Api/V1/AuthController.php (3)

- `auth.failed`
- `Logged out.`
- `None of the requested abilities are available to this account.`

## Backend: app/Http/Controllers/Api/V1/CategoryController.php (1)

- `A category called ":name" already exists.`

## Backend: app/Http/Controllers/Api/V1/PasswordController.php (1)

- `Password changed.`

## Backend: app/Http/Controllers/Api/V1/PasswordResetController.php (2)

- `That code is not valid. It may have expired — you can request a new one.`
- `We emailed you a 6-digit code.`

## Backend: app/Http/Controllers/Auth/ConfirmablePasswordController.php (1)

- `auth.password`

## Backend: app/Http/Controllers/FaqController.php (4)

- `FAQ entry created.`
- `FAQ entry deleted.`
- `FAQ entry updated.`
- `Order saved.`

## Backend: app/Http/Controllers/PageController.php (1)

- `Page saved.`

## Backend: app/Http/Controllers/SettingsController.php (2)

- `Branding updated.`
- `Colours updated.`

## Backend: app/Http/Controllers/UserController.php (6)

- `:name is now :status.`
- `:name is now verified.`
- `Permissions updated for :name.`
- `User created successfully.`
- `User deleted successfully.`
- `User updated successfully.`

## Backend: app/Http/Requests/BorrowingRequest.php (1)

- `date`

## Backend: app/Http/Requests/BrandingRequest.php (6)

- `Keep the app name under 50 characters — it has to fit the nav bar.`
- `The app name is required.`
- `The favicon must be a PNG, ICO, JPG or WebP file.`
- `The favicon must be smaller than 1 MB.`
- `The logo must be a PNG, JPG or WebP file.`
- `The logo must be smaller than 2 MB.`

## Backend: app/Http/Requests/CategoryRequest.php (5)

- `name`
- `Please pick a colour.`
- `That colour is not one of the available options.`
- `That icon is not one of the available options.`
- `The name is required.`

## Backend: app/Http/Requests/ColorRequest.php (3)

- `No label is readable on that colour (:ratio:1, needs :needs:1). Try a darker or lighter shade of it.`
- `That is not one of the available backgrounds.`
- `That is not one of the available button colours.`

## Backend: app/Http/Requests/ExpenseRequest.php (3)

- `A category called ":name" already exists — pick it from the list.`
- `item`
- `The item is required.`

## Backend: app/Http/Requests/IncomeRequest.php (1)

- `You cannot log income in the future.`

## Backend: app/Http/Requests/IncomeSourceRequest.php (1)

- `You already have a source with that name.`

## Backend: app/Http/Requests/ProfileUpdateRequest.php (1)

- `Enter a valid phone number.`

## Backend: app/Http/Requests/RecurringRuleRequest.php (4)

- `A rule cannot start more than a year ago.`
- `An income rule has no category.`
- `The end date must be after the start date.`
- `The kind of a rule cannot be changed.`

## Backend: app/Http/Requests/SavingsEntryRequest.php (1)

- `You cannot log a deposit in the future.`

## Backend: app/Http/Requests/SpendingRequest.php (1)

- `exchange rate`

## Backend: app/Http/Requests/UserRequest.php (2)

- `Please pick a role.`
- `Someone already uses that email address.`

## Backend: app/Models/IncomeSource.php (1)

- `Income source`

## Backend: app/Notifications/PasswordOtpNotification.php (4)

- `If you did not request it, no one can change your password with this email alone — you can safely ignore it.`
- `The code expires in :minutes minutes and works once.`
- `Use this code to reset your password:`
- `Your :app password reset code`

## Backend: app/Rules/UsernameRules.php (4)

- `A username may use lowercase letters, numbers, underscores and hyphens, and must start with a letter or number.`
- `A username must be at least :min characters.`
- `A username must be lowercase.`
- `Someone already uses that username.`

## Component: Components/CategoryPicker (6)

- `Create “:name”`
- `No categories found.`
- `Search the list to pick a category.`
- `Search the list, or type a name to create a category.`
- `Undo`
- `Will be added to categories for everyone.`

## Component: Components/CurrencyToggle (1)

- `Currency`

## Component: Components/DateFilter (1)

- `Done`

## Component: Components/SearchInput (1)

- `Clear search`

## Component: Components/UserStatusDialog (5)

- `Change status`
- `Confirm`
- `current`
- `For :name.`
- `They will be signed out on their next click, and any API tokens are revoked.`

## Screen: Auth/Login (1)

- `Email or username`

## Screen: Budgets/Index (7)

- `Clear this budget?`
- `Clearing…`
- `Next month`
- `No categories match your search.`
- `Previous month`
- `Set budget`
- `The budget for &quot;:name&quot; will be removed for this month. Spending is not affected.`

## Screen: Categories/Form (1)

- `Back to categories`

## Screen: Categories/Index (2)

- `&quot;:name&quot; will be removed for everyone. This cannot be undone.`
- `Delete this category?`

## Screen: Dashboard (4)

- `No month found.`
- `No year found.`
- `Search month`
- `Search year`

## Screen: Expenses/Form (1)

- `Back to expenses`

## Screen: Expenses/Index (4)

- `&quot;:item&quot; (:price) will be removed. This cannot be undone.`
- `Delete this expense?`
- `No expenses match these filters.`
- `Search expenses…`

## Screen: Profile/Partials/DeleteUserForm (1)

- `Deleting...`

## Screen: Profile/Partials/UpdateProfileInformationForm (2)

- `Optional. Lowercase letters, numbers, underscores and hyphens. You can sign in with this instead of your email.`
- `Username`

## Screen: Settings/Branding (8)

- `App name`
- `Choose file`
- `Favicon`
- `Logo`
- `PNG, JPG or WebP, up to 2 MB. Any size — it is scaled to fit.`
- `Remove`
- `Shown in the browser tab. PNG, ICO, JPG or WebP, up to 1 MB. Square works best.`
- `Shown in the nav bar and the browser tab.`

## Screen: Settings/Colors (7)

- `Button colour`
- `No label is readable on that colour (:ratio:1). Mid-tones cannot reach 4.5:1 against black or white — a darker or lighter shade will.`
- `Preview`
- `Saved`
- `System background`
- `Tints the whole page — cards, borders and labels follow. Light mode only; dark mode keeps its own.`
- `Used for primary buttons and the selected tab, in both light and dark mode. The label colour is picked automatically for contrast.`

## Screen: Settings/FaqForm (1)

- `Back to help entries`

## Screen: Settings/Spending (5)

- `Default currency`
- `Exchange rate`
- `KHR = $1.00`
- `Riel per one US dollar. A price entered in KHR is converted at this rate and stored in USD.`
- `Which currency the amount fields start on. Amounts are always stored in US dollars — this only saves retoggling when most spending is in one currency.`

## Screen: Settings/UserForm (6)

- `Add user`
- `Admins can manage categories, branding and other users.`
- `Back to users`
- `Edit user`
- `Optional. Lowercase letters, numbers, underscores and hyphens. Can also be used to sign in.`
- `Role`

## Screen: Settings/UserPermissions (5)

- `:count of :total granted`
- `Grant everything in :group`
- `Permissions`
- `Ticked by default from the :role role. Change any of them for this person only — the role is just the starting point.`
- `What :name can do beyond their own records.`

## Screen: Settings/Users (10)

- `:email will be marked as verified without the confirmation link. Only do this for an address you trust.`
- `:name and their :count expenses will be permanently deleted. This cannot be undone.`
- `Actions for :name`
- `Delete this user?`
- `unverified`
- `Verify`
- `Verify email`
- `Verify this email?`
- `Verifying…`
- `You`

