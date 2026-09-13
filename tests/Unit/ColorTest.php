<?php

namespace Tests\Unit;

use App\Support\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_contrast_matches_the_wcag_reference_values(): void
    {
        $this->assertSame(21.0, round(Color::contrast('#000000', '#ffffff'), 2));
        $this->assertSame(1.0, round(Color::contrast('#123456', '#123456'), 2));

        // The pairing that caused a real failure in this project: white on the
        // brand orange reads as obviously fine and is not.
        $this->assertLessThan(3.0, Color::contrast('#EF8519', '#ffffff'));
    }

    public function test_the_ramp_is_anchored_at_the_colour_that_was_chosen(): void
    {
        $ramp = Color::ramp('#02468B');

        $this->assertSame('#02468b', $ramp[700]);
        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950], array_keys($ramp));
    }

    public function test_the_ramp_runs_light_to_dark_without_reversing(): void
    {
        foreach (['#02468B', '#EF8519', '#0f766e', '#f4c430', '#7c3aed'] as $seed) {
            $luminance = array_map(Color::luminance(...), Color::ramp($seed));
            $sorted = $luminance;
            rsort($sorted);

            $this->assertSame($sorted, array_values($luminance), "Ramp for {$seed} is not monotonic.");
        }
    }

    public function test_the_ramp_ends_are_usable_as_a_page_tint_and_as_deep_ink(): void
    {
        foreach (['#02468B', '#EF8519', '#7c3aed'] as $seed) {
            $ramp = Color::ramp($seed);

            // 50 is a background wash: dark text must sit on it.
            $this->assertGreaterThan(Color::AA, Color::contrast($ramp[50], '#0b1b2b'), "50 too dark for {$seed}");
            // 950 is used for deep surfaces: white must sit on it.
            $this->assertGreaterThan(Color::AA, Color::contrast($ramp[950], '#ffffff'), "950 too light for {$seed}");
        }
    }

    public function test_the_ramp_keeps_the_hue_it_was_given(): void
    {
        // A blue seed must not produce green or purple steps. Mixing in sRGB
        // rather than OKLCH is what causes that, so this is the guard on it.
        $hue = fn (string $hex) => (function (array $rgb) {
            [$r, $g, $b] = $rgb;
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);

            if ($max === $min) {
                return null;
            }

            $h = match ($max) {
                $r => 60 * fmod(($g - $b) / ($max - $min), 6),
                $g => 60 * (($b - $r) / ($max - $min) + 2),
                default => 60 * (($r - $g) / ($max - $min) + 4),
            };

            return fmod($h + 360, 360);
        })([
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ]);

        $seedHue = $hue('#02468B');

        foreach (Color::ramp('#02468B') as $step => $hex) {
            $stepHue = $hue($hex);

            if ($stepHue === null) {
                continue; // A fully desaturated step has no hue to compare.
            }

            $delta = min(abs($stepHue - $seedHue), 360 - abs($stepHue - $seedHue));
            $this->assertLessThan(25, $delta, "Step {$step} ({$hex}) drifted {$delta}° from the seed hue.");
        }
    }

    public function test_ink_is_chosen_by_measurement_not_by_preference(): void
    {
        $this->assertSame('#ffffff', Color::ink('#02468B'));

        // Pale colours must get dark lettering — the whole point of deriving it.
        $this->assertSame('#0b1b2b', Color::ink('#EF8519'));
        $this->assertSame('#0b1b2b', Color::ink('#f4c430'));
        $this->assertSame('#0b1b2b', Color::ink('#ffffff'));
    }

    public function test_ink_always_clears_aa_on_the_colour_it_was_chosen_for(): void
    {
        foreach (['#02468B', '#EF8519', '#f4c430', '#7c3aed', '#22C55E', '#ffffff', '#000000'] as $hex) {
            $this->assertGreaterThanOrEqual(
                Color::AA,
                Color::contrast($hex, Color::ink($hex)),
                "No readable ink found for {$hex}.",
            );
        }
    }

    public function test_a_colour_that_already_passes_as_text_is_left_alone(): void
    {
        $this->assertSame('#02468b', Color::readable('#02468B'));
    }

    public function test_a_colour_too_light_for_text_is_darkened_until_it_passes(): void
    {
        foreach (['#EF8519', '#f4c430', '#22C55E'] as $hex) {
            $readable = Color::readable($hex);

            $this->assertNotSame(Color::normalise($hex), $readable);
            $this->assertGreaterThanOrEqual(Color::AA, Color::contrast($readable, '#ffffff'), $hex);
        }
    }

    public function test_hex_values_are_recognised_and_normalised(): void
    {
        $this->assertTrue(Color::isHex('#fff'));
        $this->assertTrue(Color::isHex('#02468B'));

        $this->assertFalse(Color::isHex(null));
        $this->assertFalse(Color::isHex(''));
        $this->assertFalse(Color::isHex('02468B'));
        $this->assertFalse(Color::isHex('#12345'));
        $this->assertFalse(Color::isHex('red'));
        $this->assertFalse(Color::isHex('#fff;}</style><script>alert(1)</script>'));

        $this->assertSame('#ffffff', Color::normalise('#FFF'));
        $this->assertSame('#02468b', Color::normalise('#02468B'));
    }
}
