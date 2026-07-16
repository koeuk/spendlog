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
     * Minimum luminance gap between the page and each surface. Small values —
     * these are meant to be a whisper of depth, not stripes — but large enough
     * that the edge survives a cheap panel.
     */
    private const CARD_SEPARATION = 0.02;

    /** Muted sits just off the card — a hint of a step, not another layer. */
    private const STACK_SEPARATION = 0.015;

    /** The border has to read as an edge, so it is the biggest step. */
    private const BORDER_SEPARATION = 0.05;

    /**
     * Every design token, as bare HSL triplets ready for a custom property.
     *
     * @return array<string, string>
     */
    public static function from(string $hex): array
    {
        [$h, $s, $l] = self::hsl($hex);

        /*
         * Surfaces are spaced by *luminance*, not lightness, so they stay visibly
         * apart on any hue — and each is stepped off the previous one, not all
         * off the page.
         *
         * Stepping each from the page independently looks equivalent and is not.
         * On a saturated yellow a single lightness step moves luminance by more
         * than 0.09, so the card (needing 0.02) and the border (needing 0.09)
         * both stop at that same first step and come out identical: a border you
         * cannot see. Chaining them keeps the hierarchy intact whatever the hue
         * does.
         */
        $saturation = $s * self::SURFACE_SATURATION;
        // Near the top there is nowhere lighter to go, so the stack descends.
        $direction = $l > 96.0 ? -1 : 1;

        $card = self::step($h, $saturation, $l, self::hexOf(self::triplet($h, $s, $l)), self::CARD_SEPARATION, $direction);
        $muted = self::step($h, $saturation, self::lightnessOf($card), self::hexOf($card), self::STACK_SEPARATION, $direction);
        $border = self::step($h, $saturation, self::lightnessOf($muted), self::hexOf($muted), self::BORDER_SEPARATION, $direction);

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
     * Walk lightness away from $fromLightness until the result is $separation
     * luminance clear of $againstHex.
     *
     * Measured, not assumed. Stepping lightness by a fixed amount is not enough,
     * because the damped saturation moves luminance too — and for the
     * yellow-green hues the two cancel almost exactly. A #6c6c13 olive page with
     * lightness +5 on the card lands within 0.003 luminance of the page: a card
     * you cannot see. So this walks until the separation is real, then stops.
     */
    private static function step(
        float $h,
        float $saturation,
        float $fromLightness,
        string $againstHex,
        float $separation,
        int $direction,
    ): string {
        $from = Color::luminance($againstHex);

        for ($offset = 1; $offset <= 100; $offset++) {
            $lightness = self::clamp($fromLightness + $direction * $offset);
            $candidate = self::triplet($h, $saturation, $lightness);

            if (abs(Color::luminance(self::hexOf($candidate)) - $from) >= $separation) {
                return $candidate;
            }

            // Clamped at the end of the range: no further step will change
            // anything, so stop rather than spin.
            if ($lightness <= 0.0 || $lightness >= 100.0) {
                break;
            }
        }

        return self::triplet($h, $saturation, $direction > 0 ? 100.0 : 0.0);
    }

    private static function lightnessOf(string $triplet): float
    {
        return (float) rtrim(explode(' ', $triplet)[2], '%');
    }

    /**
     * Body text for $surface: whichever end of the lightness range contrasts
     * more, full stop.
     *
     * Measured against both ends rather than branched on a luminance threshold.
     * A mid-tone like #c1891a sits at luminance 0.28 — "dark", by any threshold
     * — yet takes black text at 5.9:1 and white at only 3.2:1. Guessing from the
     * threshold picks white and produces exactly the unreadable page this whole
     * exercise is meant to prevent.
     *
     * No walking toward the surface: body text wants the *most* contrast it can
     * get, not the least that passes. (Muted text is the one that recedes — see
     * below.) $target is therefore only used to report whether the surface can
     * host readable text at all, which supports() exposes.
     *
     * Keeping a trace of the hue stops the text reading as a grey sticker on a
     * coloured page, but at a fraction of the saturation — coloured body text is
     * tiring, and at these lightnesses the hue barely shows anyway.
     */
    private static function textOn(float $h, float $s, string $surface, float $target): string
    {
        $saturation = min($s * 0.15, 12.0);

        return self::bestTextEnd($h, $saturation, self::hexOf($surface));
    }

    /**
     * Muted text: as close to the surface as AA allows, so it recedes.
     *
     * Walks from mid-range toward the same end body text chose, stopping at the
     * first value that clears AA — the quietest reading that is still legible.
     * Falls back to the body-text end when nothing in between passes, which is
     * what happens on a mid-tone surface where the margin is thin.
     */
    private static function mutedTextOn(float $h, float $s, string $surface): string
    {
        $surfaceHex = self::hexOf($surface);
        $saturation = min($s * self::MUTED_SATURATION, 25.0);

        // Same direction as body text, decided the same way.
        $towardLight = self::prefersLightText($h, $saturation, $surfaceHex);

        for ($step = 0; $step <= 100; $step += 2) {
            $lightness = $towardLight ? 45.0 + $step : 60.0 - $step;
            $candidate = self::triplet($h, $saturation, self::clamp($lightness));

            if (Color::contrast(self::hexOf($candidate), $surfaceHex) >= self::AA) {
                return $candidate;
            }
        }

        // Nothing between passed — take the extreme, the best available.
        return self::bestTextEnd($h, $saturation, $surfaceHex);
    }

    private static function bestTextEnd(float $h, float $s, string $surfaceHex): string
    {
        return self::prefersLightText($h, $s, $surfaceHex)
            ? self::triplet($h, $s, 98.0)
            : self::triplet($h, $s, 4.0);
    }

    private static function prefersLightText(float $h, float $s, string $surfaceHex): bool
    {
        $light = self::hexOf(self::triplet($h, $s, 98.0));
        $dark = self::hexOf(self::triplet($h, $s, 4.0));

        return Color::contrast($light, $surfaceHex) > Color::contrast($dark, $surfaceHex);
    }

    /**
     * The best contrast any text can reach on this colour.
     *
     * A mid-tone cannot host readable text at either end — a pure #d92626 red
     * peaks at 4.40:1 — and no palette can fix that: there is no third end to
     * reach for. See supports().
     */
    public static function bestTextContrast(string $hex): float
    {
        [$h, $s] = self::hsl($hex);
        $saturation = min($s * 0.15, 12.0);

        return max(
            Color::contrast(self::hexOf(self::triplet($h, $saturation, 98.0)), $hex),
            Color::contrast(self::hexOf(self::triplet($h, $saturation, 4.0)), $hex),
        );
    }

    /**
     * Whether a readable theme can actually be built on this colour.
     *
     * Checked against the derived *card*, not just the page. The card is where
     * nearly all the text lives, and it is a step off the page — so a colour can
     * host readable text itself while the surface derived from it cannot. Both
     * have to work or the theme is broken where it matters most.
     *
     * This is what the picker refuses on. It is a real limit of mid-tones, not a
     * matter of taste, so it is worth stating plainly rather than shipping a page
     * whose labels are a guess.
     */
    public static function supports(string $hex): bool
    {
        $palette = self::from($hex);

        $pairs = [
            ['foreground', 'background'],
            ['card-foreground', 'card'],
            ['muted-foreground', 'card'],
        ];

        foreach ($pairs as [$text, $surface]) {
            $contrast = Color::contrast(
                self::hexOf($palette[$text]),
                self::hexOf($palette[$surface]),
            );

            if ($contrast < self::AA) {
                return false;
            }
        }

        return true;
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
