<?php

namespace Tests\Unit;

use App\Support\Color;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hexToHsl(): array
    {
        return [
            'white' => ['#ffffff', '0 0% 100%'],
            'black' => ['#000000', '0 0% 0%'],
            'red' => ['#ff0000', '0 100% 50%'],
            'green' => ['#00ff00', '120 100% 50%'],
            'blue' => ['#0000ff', '240 100% 50%'],
            'cyan' => ['#00ffff', '180 100% 50%'],
            'magenta' => ['#ff00ff', '300 100% 50%'],
            // The token this replaces: app.css has --primary: 0 0% 9%.
            'neutral-900' => ['#171717', '0 0% 9.02%'],
            'mid grey' => ['#808080', '0 0% 50.2%'],
        ];
    }

    #[DataProvider('hexToHsl')]
    public function test_it_converts_hex_to_a_bare_hsl_triplet(string $hex, string $expected): void
    {
        $this->assertSame($expected, Color::toHslTriplet($hex));
    }

    /**
     * Greys have no hue and PHP returns an int from an exact division, so the
     * saturation denominator is zero. This used to be a DivisionByZeroError.
     */
    public function test_greys_do_not_divide_by_zero(): void
    {
        foreach (['#ffffff', '#000000', '#808080', '#171717'] as $grey) {
            $this->assertMatchesRegularExpression('/^0 0% [\d.]+%$/', Color::toHslTriplet($grey));
        }
    }

    public function test_it_is_case_insensitive(): void
    {
        $this->assertSame(Color::toHslTriplet('#ff0000'), Color::toHslTriplet('#FF0000'));
    }

    public function test_it_rejects_anything_that_is_not_a_six_digit_hex(): void
    {
        foreach (['red', '#fff', '#12345', '#1234567', 'ff0000', '', '#gggggg'] as $bad) {
            $this->assertFalse(Color::isHex($bad), "[{$bad}] should not pass isHex()");
        }

        $this->expectException(InvalidArgumentException::class);
        Color::toHslTriplet('red;} html { display: none }');
    }

    /**
     * The whole point of computing the label colour: a free picker invites a pale
     * choice, and white-on-pale loses the label entirely.
     */
    public function test_pale_fills_get_dark_text_and_dark_fills_get_light_text(): void
    {
        foreach (['#ffffff', '#fde047', '#faf8f4', '#e6f0ff'] as $pale) {
            $this->assertSame('0 0% 3.9%', Color::readableForegroundTriplet($pale), $pale);
        }

        foreach (['#000000', '#171717', '#0000ff', '#4b0082'] as $dark) {
            $this->assertSame('0 0% 98%', Color::readableForegroundTriplet($dark), $dark);
        }
    }

    /**
     * The guarantee is "the better of the two", not "always AA".
     *
     * Mid-tone fills genuinely cannot clear 4.5:1 against either near-black or
     * near-white — #ad661f tops out at 4.43 — so no choice of label colour would
     * pass, and asserting AA here would be asserting something impossible. What
     * must hold is that we never pick the worse of the two options.
     */
    public function test_the_computed_label_is_always_the_higher_contrast_option(): void
    {
        // Every hue at a few lightnesses, rather than a handful of favourites.
        foreach (range(0, 350, 10) as $hue) {
            foreach ([20, 40, 60, 80] as $lightness) {
                $hex = self::hslToHex($hue, 70, $lightness);

                $chosen = Color::readableForegroundTriplet($hex) === '0 0% 3.9%'
                    ? '#0a0a0a'
                    : '#fafafa';
                $rejected = $chosen === '#0a0a0a' ? '#fafafa' : '#0a0a0a';

                $this->assertGreaterThanOrEqual(
                    Color::contrast($hex, $rejected),
                    Color::contrast($hex, $chosen),
                    "{$hex}: picked {$chosen} when {$rejected} contrasts better",
                );
            }
        }
    }

    /**
     * Documents the ceiling rather than pretending it does not exist: a mid-tone
     * fill is a bad button colour whatever the label does, which is a UI warning
     * to add, not something this function can solve.
     */
    public function test_mid_tone_fills_cannot_reach_aa_with_either_label(): void
    {
        $best = max(
            Color::contrast('#ad661f', '#0a0a0a'),
            Color::contrast('#ad661f', '#fafafa'),
        );

        $this->assertLessThan(4.5, $best);
    }

    public function test_contrast_is_symmetric_and_bounded(): void
    {
        $this->assertEqualsWithDelta(21.0, Color::contrast('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, Color::contrast('#4b9d5f', '#4b9d5f'), 0.01);
        $this->assertEqualsWithDelta(
            Color::contrast('#123456', '#abcdef'),
            Color::contrast('#abcdef', '#123456'),
            0.0001,
        );
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }
}
