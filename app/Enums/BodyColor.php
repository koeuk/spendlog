<?php

namespace App\Enums;

/**
 * The five offered page backgrounds.
 *
 * Not a storage constraint — body_color accepts any hex, because the picker also
 * takes a custom value. These are the presets the UI offers, kept here so the
 * default in the migration and the swatches on the page cannot drift apart.
 *
 * All five are near-white on purpose. The page is a backdrop for glass cards
 * that carry their own translucent fill; a saturated body colour would show
 * through every one of them and fight the category colours the charts depend on.
 */
enum BodyColor: string
{
    // The default background, and first in the list because of it: a soft, thin
    // silver the theme renders with pure white cards floating on it — the
    // grey-page, white-card look. Neutral, not tinted like the rest. See the
    // whiteCards path in Palette, the Silver checks in AppSetting, and the
    // .solid-cards rule in app.css.
    case Silver = '#f4f5f7';
    // The ambient-wash look: the soft green blobs are what White means. It is the
    // one background that keeps the wash — every other, Silver included, is flat.
    case White = '#ffffff';
    case Cream = '#faf8f4';
    case Sand = '#f7f4ee';
    case Blush = '#fbf5f6';
    case Rose = '#f9f2f4';
    case Sage = '#f3f7f3';
    case Mint = '#f0f7f4';
    case Mist = '#f5f7f9';
    case Sky = '#f1f6fb';
    case Lilac = '#f6f4fa';

    public function label(): string
    {
        return match ($this) {
            self::Silver => 'Silver',
            self::White => 'White',
            self::Cream => 'Cream',
            self::Sand => 'Sand',
            self::Blush => 'Blush',
            self::Rose => 'Rose',
            self::Sage => 'Sage',
            self::Mint => 'Mint',
            self::Mist => 'Mist',
            self::Sky => 'Sky',
            self::Lilac => 'Lilac',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function presets(): array
    {
        return array_map(
            fn (self $color) => ['value' => $color->value, 'label' => $color->label()],
            self::cases(),
        );
    }
}
