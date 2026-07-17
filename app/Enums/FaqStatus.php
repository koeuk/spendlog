<?php

namespace App\Enums;

/**
 * A FAQ entry is either a draft the admin is still working on, or published and
 * visible on the help page. Nothing in between — there is no scheduling here.
 */
enum FaqStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
        };
    }
}
