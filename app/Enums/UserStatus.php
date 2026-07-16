<?php

namespace App\Enums;

/**
 * Whether an account may be used.
 *
 * Only Active can sign in. The other three are distinct on purpose — they mean
 * different things to whoever is reading the list, even though all three block
 * access identically:
 *
 *   Invited   — created, not started. Expected to become Active.
 *   Suspended — was Active, blocked for now. Expected to come back.
 *   Archived  — was Active, gone for good. Kept because an expense list is
 *               someone's financial history, and deleting the row would take
 *               that with it.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Invited => __('Invited'),
            self::Suspended => __('Suspended'),
            self::Archived => __('Archived'),
        };
    }

    /** Shown in the confirm dialog, so the consequence is stated before the click. */
    public function description(): string
    {
        return match ($this) {
            self::Active => __('Can sign in and use the app normally.'),
            self::Invited => __('Cannot sign in yet. Use this for an account that has been set up but not handed over.'),
            self::Suspended => __('Cannot sign in. Any open session ends on their next click, and API tokens are revoked.'),
            self::Archived => __('Cannot sign in, and the account is treated as closed. Their expenses are kept.'),
        };
    }

    /** Tailwind classes for the badge, spelled out so Tailwind sees them. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-green-100 text-green-700 dark:bg-green-400/15 dark:text-green-300',
            self::Invited => 'bg-blue-100 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300',
            self::Suspended => 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300',
            self::Archived => 'bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300',
        };
    }

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }

    /**
     * Whether moving to this status should cut existing access. Every non-active
     * status does — a suspended user with a live session is not suspended.
     */
    public function revokesAccess(): bool
    {
        return ! $this->canSignIn();
    }

    /** The message shown at the login screen for a blocked account. */
    public function signInError(): string
    {
        return match ($this) {
            self::Active => '',
            self::Invited => __('Your account is not active yet. Please contact an administrator.'),
            self::Suspended => __('Your account has been suspended. Please contact an administrator.'),
            self::Archived => __('This account has been closed. Please contact an administrator.'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, can_sign_in: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'can_sign_in' => $status->canSignIn(),
            ],
            self::cases(),
        );
    }
}
