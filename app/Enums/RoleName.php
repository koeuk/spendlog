<?php

namespace App\Enums;

enum RoleName: string
{
    /**
     * The owner account. Holds everything an admin does — the difference is not
     * power but reach: nothing in the user-management screen can touch it. See
     * UserPolicy.
     */
    case SuperAdmin = 'super_admin';

    case Admin = 'admin';
    case User = 'user';

    /**
     * The roles that may be handed out through the app.
     *
     * Super admin is deliberately absent. If it appeared in the dropdown, an
     * admin could mint an account that no admin — including themselves — could
     * ever edit, delete or demote again. It is granted from the seeder or
     * `users:promote` only, which needs server access.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role) => $role !== self::SuperAdmin,
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super admin'),
            self::Admin => __('Admin'),
            self::User => __('User'),
        };
    }
}
