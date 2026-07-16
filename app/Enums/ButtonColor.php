<?php

namespace App\Enums;

use App\Models\AppSetting;

/**
 * The offered button colours.
 *
 * Every one is dark enough to take a near-white label at AA or better, which is
 * the thing a free picker cannot promise: a mid-tone like #d92626 tops out
 * around 4.4:1 against *any* label, so the picker would happily hand back a
 * button nobody can read. Choosing from a set that has been checked once is the
 * simpler guarantee.
 *
 * Ink is the default and means "no brand colour" — it is the one value that
 * leaves --primary as the theme-aware pair app.css defines (near-black on a
 * light page, near-white on a dark one). See AppSetting::cssVariables().
 */
enum ButtonColor: string
{
    case Ink = '#171717';
    case Green = '#2f6f43';
    case Teal = '#0f766e';
    case Blue = '#1d4ed8';
    case Indigo = '#4338ca';
    case Purple = '#6d28d9';
    case Red = '#b42727';
    case Amber = '#92400e';

    public function label(): string
    {
        return match ($this) {
            self::Ink => 'Ink',
            self::Green => 'Green',
            self::Teal => 'Teal',
            self::Blue => 'Blue',
            self::Indigo => 'Indigo',
            self::Purple => 'Purple',
            self::Red => 'Red',
            self::Amber => 'Amber',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, is_default: bool}>
     */
    public static function presets(): array
    {
        return array_map(
            fn (self $color) => [
                'value' => $color->value,
                'label' => $color->label(),
                // The page marks this one "Default" rather than making the admin
                // guess which swatch means "leave it alone".
                'is_default' => $color->value === AppSetting::DEFAULT_BUTTON_COLOR,
            ],
            self::cases(),
        );
    }
}
