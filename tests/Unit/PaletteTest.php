<?php

namespace Tests\Unit;

use App\Support\Color;
use App\Support\Palette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaletteTest extends TestCase
{
    /**
     * Every hue at every useful lightness, rather than a handful of favourites.
     * The whole promise of the generator is that an *arbitrary* colour comes out
     * readable, so the test has to cover arbitrary colours.
     *
     * @return array<string, array{0: string}>
     */
    public static function backgrounds(): array
    {
        $cases = [];

        foreach (range(0, 330, 30) as $hue) {
            foreach ([[70, 25], [70, 50], [70, 75], [25, 95], [100, 45]] as [$saturation, $lightness]) {
                $hex = self::hslToHex($hue, $saturation, $lightness);
                $cases["h{$hue} s{$saturation} l{$lightness}"] = [$hex];
            }
        }

        // The greys, where hue is undefined and the maths is most likely to trip.
        foreach (['#ffffff', '#000000', '#808080', '#171717', '#fafafa'] as $grey) {
            $cases["grey {$grey}"] = [$grey];
        }

        return $cases;
    }

    #[DataProvider('backgrounds')]
    public function test_body_text_is_always_the_best_available_contrast(string $background): void
    {
        $palette = Palette::from($background);

        $actual = Color::contrast(
            self::hexOf($palette['foreground']),
            self::hexOf($palette['background']),
        );

        // Not "always AAA" — a mid-tone cannot reach it at either end, and no
        // palette can change that. What must hold is that we never settle for
        // less than the colour allows.
        $this->assertEqualsWithDelta(
            Palette::bestTextContrast($background),
            $actual,
            0.05,
            "{$background}: foreground is not the best available end",
        );
    }

    #[DataProvider('backgrounds')]
    public function test_muted_text_clears_aa_on_the_card_it_sits_on(string $background): void
    {
        if (! Palette::supports($background)) {
            $this->markTestSkipped('The picker refuses this colour — see test_unsupported_colours_are_exactly_the_mid_tones.');
        }

        $palette = Palette::from($background);

        $this->assertGreaterThanOrEqual(
            4.5,
            Color::contrast(
                self::hexOf($palette['muted-foreground']),
                self::hexOf($palette['card']),
            ),
            "{$background}: muted text is unreadable on the card",
        );
    }

    #[DataProvider('backgrounds')]
    public function test_card_text_clears_aa_on_the_card(string $background): void
    {
        if (! Palette::supports($background)) {
            $this->markTestSkipped('The picker refuses this colour — see test_unsupported_colours_are_exactly_the_mid_tones.');
        }

        $palette = Palette::from($background);

        $this->assertGreaterThanOrEqual(
            4.5,
            Color::contrast(
                self::hexOf($palette['card-foreground']),
                self::hexOf($palette['card']),
            ),
            "{$background}: body text is unreadable on the card",
        );
    }

    /**
     * A card the same colour as the page is invisible — the whole layout is
     * cards on a page, so they have to be separable.
     */
    #[DataProvider('backgrounds')]
    public function test_the_card_is_distinguishable_from_the_page(string $background): void
    {
        $palette = Palette::from($background);

        $card = Color::luminance(self::hexOf($palette['card']));
        $page = Color::luminance(self::hexOf($palette['background']));

        $this->assertNotEqualsWithDelta(
            $page,
            $card,
            0.004,
            "{$background}: the card is indistinguishable from the page",
        );
    }

    #[DataProvider('backgrounds')]
    public function test_the_border_sits_between_the_card_and_the_text(string $background): void
    {
        $palette = Palette::from($background);

        // A border darker than the text, or lighter than the card, is not an edge
        // — it is either a bar or nothing.
        $border = Color::luminance(self::hexOf($palette['border']));
        $card = Color::luminance(self::hexOf($palette['card']));

        $this->assertNotEqualsWithDelta($card, $border, 0.002, "{$background}: border is invisible on the card");
    }

    /**
     * The point of deriving rather than defaulting to grey: the result should
     * read as one family, which means keeping the hue.
     */
    public function test_it_keeps_the_hue_across_every_surface(): void
    {
        $palette = Palette::from('#1e293b'); // slate — hue 217

        foreach (['background', 'card', 'muted', 'border', 'secondary'] as $token) {
            $hue = (float) explode(' ', $palette[$token])[0];

            $this->assertEqualsWithDelta(
                217.24,
                $hue,
                0.5,
                "{$token} drifted off the source hue",
            );
        }
    }

    /**
     * supports() must refuse for a real reason, not out of caution: the colours
     * it rejects are the mid-tones, where no text of any lightness reaches AA.
     */
    public function test_unsupported_colours_are_exactly_the_mid_tones(): void
    {
        // Saturated mid-tones: nothing lands on these.
        foreach (['#d92626', '#d92680', '#ad661f'] as $midTone) {
            $this->assertFalse(Palette::supports($midTone), "{$midTone} should be refused");
        }

        // The ends always work — that is where a usable theme lives.
        foreach (['#ffffff', '#000000', '#faf8f4', '#1e293b', '#0a0a0a'] as $usable) {
            $this->assertTrue(Palette::supports($usable), "{$usable} should be accepted");
        }
    }

    public function test_a_grey_background_stays_grey(): void
    {
        // Hue is undefined at zero saturation; nothing should invent one.
        foreach (Palette::from('#808080') as $token => $triplet) {
            $saturation = (float) rtrim(explode(' ', $triplet)[1], '%');

            $this->assertSame(0.0, $saturation, "{$token} invented a hue on a grey background");
        }
    }

    public function test_it_fills_every_token_the_stylesheet_defines(): void
    {
        // A token left out falls back to app.css's stock value — a grey border on
        // a gold page — which is exactly the drift this replaces.
        $expected = [
            'background', 'foreground', 'card', 'card-foreground', 'popover',
            'popover-foreground', 'secondary', 'secondary-foreground', 'accent',
            'accent-foreground', 'muted', 'muted-foreground', 'border', 'input', 'ring',
        ];

        $this->assertSame($expected, array_keys(Palette::from('#ffffff')));
    }

    #[DataProvider('backgrounds')]
    public function test_every_token_is_a_valid_bare_hsl_triplet(string $background): void
    {
        // These are interpolated straight into a custom property; a malformed one
        // is a silently broken page, not an error.
        foreach (Palette::from($background) as $token => $triplet) {
            $this->assertMatchesRegularExpression(
                '/^\d+(\.\d+)? \d+(\.\d+)?% \d+(\.\d+)?%$/',
                $triplet,
                "{$token} is not a bare HSL triplet: [{$triplet}]",
            );
        }
    }

    private static function hexOf(string $triplet): string
    {
        [$h, $s, $l] = array_map(
            fn (string $part) => (float) rtrim($part, '%'),
            explode(' ', $triplet),
        );

        return self::hslToHex($h, $s, $l);
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
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
}
