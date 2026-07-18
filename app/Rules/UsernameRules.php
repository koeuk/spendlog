<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * What a username may be, in one place.
 *
 * Two forms accept one — an admin creating or editing an account, and a person
 * editing their own profile — and a handle that is valid on one screen and
 * rejected on the other is a bug waiting to be filed. Same shape as
 * CalendarOptions: shared so the two cannot drift.
 *
 * A display handle, not a credential. Signing in stays email and password.
 */
class UsernameRules
{
    /** Long enough to be a name, short enough to sit in a table cell. */
    public const MIN = 3;

    public const MAX = 30;

    /**
     * @param  int|null  $ignoreUserId  The row being edited, so its own username
     *                                  does not collide with itself.
     * @return array<int, mixed>
     */
    public static function for(?int $ignoreUserId = null): array
    {
        return [
            // Optional everywhere: the column is nullable, and nobody should be
            // made to invent a handle to save an unrelated change.
            'nullable',
            'string',
            'lowercase',
            'min:'.self::MIN,
            'max:'.self::MAX,
            /*
             * Letters, digits, underscore and hyphen, starting with a letter or
             * digit. Excludes '.' and '@' deliberately: a username that can look
             * like an email address invites "why can't I log in with it", and a
             * trailing dot breaks routes that end in a format extension.
             */
            'regex:/^[a-z0-9][a-z0-9_-]*$/',
            Rule::unique(User::class, 'username')->ignore($ignoreUserId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'username'): array
    {
        return [
            $attribute.'.regex' => __('A username may use lowercase letters, numbers, underscores and hyphens, and must start with a letter or number.'),
            $attribute.'.lowercase' => __('A username must be lowercase.'),
            $attribute.'.unique' => __('Someone already uses that username.'),
            $attribute.'.min' => __('A username must be at least :min characters.', ['min' => self::MIN]),
        ];
    }

    /**
     * Blank arrives from a form as '', which would hit the unique index as a
     * real value and let exactly one account hold the empty username. Null is
     * what "not set" means in the column.
     */
    public static function normalize(?string $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
