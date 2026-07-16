<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Khmer = 'km';

    /** Shown in the language switcher. */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Khmer => 'ខ្មែរ',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::English => 'EN',
            self::Khmer => 'KM',
        };
    }
}
