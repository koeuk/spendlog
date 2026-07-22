<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Concerns\HasUuidRouteKey;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Implementing MustVerifyEmail is what makes the Registered event actually send
 * the verification mail, and what gives the 'verified' middleware teeth — without
 * it that middleware silently lets everyone through.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuidRouteKey, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'google_id',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Put the account on a role and give it that role's starting permissions.
     *
     * Always both. A role grants nothing at run time (see RoleSeeder), so
     * syncRoles() on its own leaves the account able to do literally nothing —
     * which is how self-registration shipped broken. Anything that sets a role
     * goes through here so that cannot happen again.
     *
     * The permissions are a starting set, not a binding: they can be edited per
     * person afterwards, and re-applying a role resets them to the defaults.
     */
    public function applyRole(RoleName $role): void
    {
        $this->syncRoles([$role->value]);
        $this->syncPermissions(Permission::defaultsFor($role));
    }

    /**
     * The view permission guarding each of the app's main pages, ordered from
     * the most representative page to the least. Doubles as the landing-page
     * preference order.
     */
    private const PAGE_PERMISSIONS = [
        'dashboard' => Permission::DashboardView,
        'expenses.index' => Permission::ExpensesView,
        'budgets.index' => Permission::BudgetsView,
        'reports.index' => Permission::ReportsView,
        'categories.index' => Permission::CategoriesView,
    ];

    /**
     * The first page this account is actually allowed to open.
     *
     * The dashboard is the front door for almost everyone, but it is a revocable
     * permission like any other, and redirecting an account there unconditionally
     * turns "no dashboard" into "no app at all" — the login lands on a 403 with
     * nowhere to go.
     */
    public function homeRoute(): string
    {
        foreach (self::PAGE_PERMISSIONS as $route => $permission) {
            if ($this->hasPermissionTo($permission->value)) {
                return $route;
            }
        }

        // Nothing left to show. Profile needs no permission, so an account
        // stripped of every view still lands somewhere it can sign out from.
        return 'profile.edit';
    }

    /**
     * Whether this account may open a named page.
     *
     * Only the pages in the map are gated; anything else (profile, password) is
     * open to any signed-in account, so an unknown name is not a refusal.
     */
    public function canOpenRoute(?string $route): bool
    {
        $permission = self::PAGE_PERMISSIONS[$route] ?? null;

        return $permission === null || $this->hasPermissionTo($permission->value);
    }

    /**
     * Administers the app — either role.
     *
     * A super admin is included on purpose. This backs the API-docs gate, the
     * shared `is_admin` flag and the token abilities, and if it asked for the
     * admin role alone a super admin would come out *less* capable than an
     * admin: no docs, no admin UI, a narrower token. "Super" would be a lie.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value]);
    }

    /**
     * The owner account, which the user-management screen cannot touch at all.
     *
     * Deliberately not folded into isAdmin(): every caller of that one is asking
     * "may this person administer the app", and the answer is the same for both.
     * This asks the different question — "is this person out of reach" — and only
     * UserPolicy cares.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /**
     * The movements this person invented — *not* what they can log against.
     *
     * The global catalogue has a null user_id and so is deliberately absent
     * here: this relationship is the write path (new types are created through
     * it, which is what keeps user_id out of the fillable list). For the read
     * path use ExerciseType::availableTo(), which unions both.
     */
    public function exerciseTypes(): HasMany
    {
        return $this->hasMany(ExerciseType::class);
    }
}
