<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Hex → the HSL triplets the design tokens are written in.
 *
 * resources/css/app.css stores colours as bare triplets ("0 0% 9%") and wraps
 * them at use with hsl(var(--primary)). That indirection is what lets a token be
 * reused at partial alpha — hsl(var(--primary) / 0.5). So an admin's '#171717'
 * cannot be dropped into the variable as-is; it has to be converted, or every
 * hsl() referencing it resolves to nothing and the colour silently disappears.
 */
final class Color
{
    public const HEX_PATTERN = '/^#[0-9a-f]{6}$/i';

    public static function isHex(string $value): bool
    {
        return (bool) preg_match(self::HEX_PATTERN, $value);
    }

    /**
     * '#171717' → '0 0% 9%' — the bare triplet the tokens expect, with no hsl()
     * wrapper and no commas.
     */
    public static function toHslTriplet(string $hex): string
    {
        [$r, $g, $b] = self::toRgb($hex);

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;

        if ($delta === 0.0) {
            // Grey: hue is undefined and saturation is zero. Any hue renders the
            // same, so 0 keeps the output stable rather than arbitrary.
            return sprintf('0 0%% %s%%', self::trim($lightness * 100));
        }

        $saturation = $lightness > 0.5
            ? $delta / (2 - $max - $min)
            : $delta / ($max + $min);

        $hue = match ($max) {
            $r => (($g - $b) / $delta) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $delta) + 2,
            default => (($r - $g) / $delta) + 4,
        } * 60;

        return sprintf(
            '%s %s%% %s%%',
            self::trim($hue),
            self::trim($saturation * 100),
            self::trim($lightness * 100),
        );
    }

    /**
     * The triplet for text sitting on $hex — whichever of near-black or
     * near-white contrasts more.
     *
     * Without this, an admin picking a pale button colour gets white-on-pale and
     * an unreadable label, which is exactly the mistake a free colour picker
     * invites.
     */
    public static function readableForegroundTriplet(string $hex): string
    {
        // The same near-black / near-white the light and dark tokens already use,
        // rather than pure #000/#fff, which read as harsh against a tinted fill.
        return self::contrast($hex, '#0a0a0a') >= self::contrast($hex, '#fafafa')
            ? '0 0% 3.9%'
            : '0 0% 98%';
    }

    /**
     * WCAG 2.x contrast ratio, 1..21.
     */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        [$lighter, $darker] = $la > $lb ? [$la, $lb] : [$lb, $la];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * WCAG relative luminance, 0..1.
     */
    public static function luminance(string $hex): float
    {
        $linear = array_map(
            // sRGB gamma decode: the channel values are perceptual, and summing
            // them without linearising would overweight blue.
            fn (float $channel) => $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            self::toRgb($hex),
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * @return array{0: float, 1: float, 2: float} each 0..1
     */
    private static function toRgb(string $hex): array
    {
        if (! self::isHex($hex)) {
            throw new InvalidArgumentException("Not a 6-digit hex colour: [{$hex}]");
        }

        $int = hexdec(ltrim($hex, '#'));

        // Cast every channel: PHP's / returns an int when both operands are ints
        // and the division is exact, so #ffffff would yield int(1) and #000000
        // int(0). The grey short-circuit below compares against 0.0 strictly, and
        // an int(0) delta slips past it straight into a division by zero.
        return [
            (float) ((($int >> 16) & 255) / 255),
            (float) ((($int >> 8) & 255) / 255),
            (float) (($int & 255) / 255),
        ];
    }

    /**
     * Two decimals at most, and no trailing zeros — '0 0% 9%', not '0.00 0.00% 9.00%'.
     */
    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
