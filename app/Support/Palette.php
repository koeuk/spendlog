<?php

namespace App\Support;

/**
 * Derives a whole theme from one background colour.
 *
 * The problem this solves: setting only --background paints the page and leaves
 * everything else behind. Cards stay translucent white, text stays near-black,
 * borders stay grey — so a gold page gets washed-out gold cards and unreadable
 * muted text. Every token has to move together or none of them should.
 *
 * The method is relative lightness. Take the chosen colour's hue and saturation,
 * then place every surface and text token at a fixed lightness *distance* from
 * it, in the direction that has somewhere to go:
 *
 *   - a light background steps its surfaces UP toward white and its text DOWN;
 *   - a dark background steps its surfaces UP away from black and its text UP.
 *
 * Hue is preserved throughout, so the result reads as one family rather than
 * grey boxes sitting on a coloured page. Saturation is damped on large surfaces
 * — a full-strength fill is fine for a 40px button and exhausting for a whole
 * card — and every text token is contrast-checked against the surface it lands
 * on, then pushed until it passes. That check is what makes an arbitrary colour
 * safe: the palette bends rather than the text becoming unreadable.
 */
final class Palette
{
    /** Below this lightness the background is treated as a dark theme. */
    private const DARK_BELOW = 50.0;

    /** WCAG AA for body text; AAA for the primary text token. */
    private const AA = 4.5;

    private const AAA = 7.0;

    /**
     * Large surfaces carry at most this much of the source's saturation. A wall
     * of fully-saturated colour is fatiguing where a small accent is not.
     */
    private const SURFACE_SATURATION = 0.55;

    private const MUTED_SATURATION = 0.35;

    /**
     * Every design token, as bare HSL triplets ready for a custom property.
     *
     * @return array<string, string>
     */
    public static function from(string $hex): array
    {
        [$h, $s, $l] = self::hsl($hex);

        $dark = $l < self::DARK_BELOW;

        // Surfaces step toward the light on a dark theme, and toward white on a
        // light one — always the direction with room to move.
        $card = self::surface($h, $s, $l, $dark ? 5.0 : 3.0, $dark);
        $muted = self::surface($h, $s, $l, $dark ? 9.0 : 5.0, $dark);
        $border = self::surface($h, $s, $l, $dark ? 16.0 : 10.0, $dark);

        $background = self::triplet($h, $s, $l);
        $foreground = self::textOn($h, $s, $background, self::AAA);
        $cardForeground = self::textOn($h, $s, $card, self::AAA);

        return [
            'background' => $background,
            'foreground' => $foreground,

            // Popovers share the card's surface: they sit above it, and a third
            // slightly-different white is noise, not hierarchy.
            'card' => $card,
            'card-foreground' => $cardForeground,
            'popover' => $card,
            'popover-foreground' => $cardForeground,

            // Secondary and accent are the same step as muted — they differ in
            // meaning, not in depth, and the stock palette treats them alike.
            'secondary' => $muted,
            'secondary-foreground' => self::textOn($h, $s, $muted, self::AA),
            'accent' => $muted,
            'accent-foreground' => self::textOn($h, $s, $muted, self::AA),

            'muted' => $muted,
            // Deliberately only AA, and measured against the card rather than the
            // page: muted text is mostly labels sitting on a card, and pushing it
            // to AAA would make it as loud as the text it is meant to sit under.
            'muted-foreground' => self::mutedTextOn($h, $s, $card),

            'border' => $border,
            'input' => $border,
            'ring' => $foreground,
        ];
    }

    /**
     * A surface $delta lightness away from the background, in the direction that
     * has room, with its saturation damped.
     */
    private static function surface(float $h, float $s, float $l, float $delta, bool $dark): string
    {
        $saturation = $s * self::SURFACE_SATURATION;

        // Near the top of the range there is nowhere lighter to go, so step down
        // instead — otherwise a white page and a white card become one flat field
        // with no edge between them.
        $lightness = $dark || $l > 96.0
            ? $l + ($l > 96.0 ? -$delta : $delta)
            : min(100.0, $l + $delta);

        return self::triplet($h, $saturation, self::clamp($lightness));
    }

    /**
     * Text for $surface: start at the far end of the lightness range and walk
     * toward the surface only as far as the contrast target allows.
     *
     * Keeping a trace of the hue stops the text reading as a grey sticker on a
     * coloured page, but at a fraction of the saturation — coloured body text is
     * tiring, and at these lightnesses the hue barely shows anyway.
     */
    private static function textOn(float $h, float $s, string $surface, float $target): string
    {
        $surfaceLuminance = Color::luminance(self::hexOf($surface));
        $saturation = min($s * 0.15, 12.0);

        // Start from whichever end contrasts more, then close the gap.
        $towardLight = $surfaceLuminance < 0.4;

        for ($step = 0; $step <= 100; $step += 2) {
            $lightness = $towardLight ? 98.0 - $step : 4.0 + $step;
            $candidate = self::triplet($h, $saturation, self::clamp($lightness));

            if (Color::contrast(self::hexOf($candidate), self::hexOf($surface)) < $target) {
                // One step back — the last value that still passed.
                $lightness = $towardLight ? $lightness + 2 : $lightness - 2;

                return self::triplet($h, $saturation, self::clamp($lightness));
            }
        }

        return self::triplet($h, $saturation, $towardLight ? 98.0 : 4.0);
    }

    /**
     * Muted text: as close to the surface as AA allows, so it recedes.
     */
    private static function mutedTextOn(float $h, float $s, string $surface): string
    {
        $surfaceHex = self::hexOf($surface);
        $towardLight = Color::luminance($surfaceHex) < 0.4;
        $saturation = min($s * self::MUTED_SATURATION, 25.0);

        // Walk in from the surface until it just clears AA — the first passing
        // value is the quietest one that is still readable.
        for ($step = 0; $step <= 100; $step += 2) {
            $lightness = $towardLight ? 45.0 + $step : 60.0 - $step;
            $candidate = self::triplet($h, $saturation, self::clamp($lightness));

            if (Color::contrast(self::hexOf($candidate), $surfaceHex) >= self::AA) {
                return $candidate;
            }
        }

        return self::triplet($h, $saturation, $towardLight ? 98.0 : 4.0);
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function hsl(string $hex): array
    {
        $parts = explode(' ', Color::toHslTriplet($hex));

        return [
            (float) $parts[0],
            (float) rtrim($parts[1], '%'),
            (float) rtrim($parts[2], '%'),
        ];
    }

    private static function triplet(float $h, float $s, float $l): string
    {
        return sprintf('%s %s%% %s%%', self::round($h), self::round($s), self::round($l));
    }

    /**
     * Round-trips a triplet back to hex so Color can measure it — the contrast
     * maths works in hex, and these are generated values, not user input.
     */
    private static function hexOf(string $triplet): string
    {
        [$h, $s, $l] = array_map(
            fn (string $part) => (float) rtrim($part, '%'),
            explode(' ', $triplet),
        );

        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }

    private static function clamp(float $lightness): float
    {
        return max(0.0, min(100.0, $lightness));
    }

    private static function round(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
