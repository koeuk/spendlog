<?php

namespace App\Enums;

/**
 * Whether an account may be used.
 *
 * Suspension is reversible and keeps the row, unlike deleting: an expense list is
 * someone's financial history, and a fired employee's records usually have to
 * outlive their access to them.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
        };
    }

    /** Tailwind classes for the badge, spelled out so Tailwind sees them. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-green-100 text-green-700 dark:bg-green-400/15 dark:text-green-300',
            self::Suspended => 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300',
        };
    }

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
