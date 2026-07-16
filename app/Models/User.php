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
        'email',
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

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin->value);
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
}
