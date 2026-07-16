<?php

namespace App\Enums;

/**
 * Values match Tailwind palette names. The frontend maps each to a static
 * class string — Tailwind cannot see classes built dynamically at runtime.
 */
enum CategoryColor: string
{
    case Slate = 'slate';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Purple = 'purple';
    case Pink = 'pink';
}
