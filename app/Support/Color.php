<?php

namespace App\Support;

/**
 * Colour maths for the admin-chosen public theme.
 *
 * An administrator picks one colour; the public site needs a whole ramp plus
 * text colours that are still legible on it. Guessing any of that is how a
 * site ends up with white lettering on orange at 2.6:1, so every value here is
 * derived and every text pairing is checked against WCAG before it is used.
 *
 * Ramp steps are produced in OKLCH, which keeps the hue fixed while lightness
 * moves — mixing in sRGB instead turns tints muddy and shifts the hue.
 */
final class Color
{
    /**
     * How far each step travels from the chosen colour, 700 being the colour
     * itself. The public chrome is overwhelmingly `700`, so anchoring there
     * means the site shows exactly what was picked rather than an approximation
     * snapped onto a fixed lightness curve.
     */
    private const TINTS = [50 => 0.95, 100 => 0.88, 200 => 0.75, 300 => 0.60, 400 => 0.42, 500 => 0.26, 600 => 0.12];

    private const SHADES = [800 => 0.15, 900 => 0.30, 950 => 0.52];

    /** Minimum contrast for body text — WCAG 2.2 AA, 1.4.3. */
    public const AA = 4.5;

    /**
     * A full 50–950 ramp whose 700 step is the given colour exactly.
     *
     * @return array<int, string>
     */
    public static function ramp(string $hex): array
    {
        [$l, $c, $h] = self::toOklch($hex);
        $ramp = [];

        foreach (self::TINTS as $step => $t) {
            // Toward white. Chroma is reduced but not to zero, or the pale
            // steps read as plain grey instead of a tint of the brand.
            $ramp[$step] = self::fromOklch($l + (1 - $l) * $t, $c * (1 - $t * 0.85), $h);
        }

        $ramp[700] = self::normalise($hex);

        foreach (self::SHADES as $step => $t) {
            $ramp[$step] = self::fromOklch($l * (1 - $t), $c * (1 - $t * 0.5), $h);
        }

        ksort($ramp);

        return $ramp;
    }

    /**
     * Black or white — whichever is actually readable on this colour.
     *
     * White is preferred on a tie because it matches the rest of the chrome,
     * but only when it genuinely wins.
     */
    public static function ink(string $background): string
    {
        $onWhite = self::contrast($background, '#ffffff');
        $onDark = self::contrast($background, '#0b1b2b');

        return $onWhite >= $onDark ? '#ffffff' : '#0b1b2b';
    }

    /**
     * The same hue, darkened until it is legible as text on `$on`.
     *
     * Returned for the cases where the brand colour has to carry words rather
     * than sit behind them. A colour that already passes is returned unchanged.
     */
    public static function readable(string $hex, string $on = '#ffffff', float $ratio = self::AA): string
    {
        [$l, $c, $h] = self::toOklch($hex);
        $candidate = self::normalise($hex);

        // 60 steps of 1.5% lightness reaches black from anywhere, so the loop
        // always terminates with the best available answer.
        for ($i = 0; $i < 60 && self::contrast($candidate, $on) < $ratio; $i++) {
            $l = max(0.0, $l - 0.015);
            $candidate = self::fromOklch($l, $c, $h);
        }

        return $candidate;
    }

    /**
     * The same hue, moved until it is legible *on* the given background.
     *
     * `readable()` only ever darkens, which is the right move on a white page
     * and the wrong one on a dark footer. This picks the direction that can
     * actually reach the ratio, so it works on either ground.
     */
    public static function against(string $background, string $seed, float $ratio = self::AA): string
    {
        [$l, $c, $h] = self::toOklch($seed);
        $candidate = self::normalise($seed);

        // Whichever extreme contrasts more is the direction with room to move.
        $lighten = self::contrast($background, '#ffffff') >= self::contrast($background, '#000000');
        $step = $lighten ? 0.0125 : -0.0125;

        for ($i = 0; $i < 80 && self::contrast($candidate, $background) < $ratio; $i++) {
            $l = max(0.0, min(1.0, $l + $step));
            $candidate = self::fromOklch($l, $c, $h);
        }

        return $candidate;
    }

    /**
     * Blend two colours in OKLab, where a halfway mix looks halfway.
     *
     * @param float $weight share of `$a`, 0–1
     */
    public static function mix(string $a, string $b, float $weight = 0.5): string
    {
        $weight = max(0.0, min(1.0, $weight));

        [$al, $ac, $ah] = self::toOklch($a);
        [$bl, $bc, $bh] = self::toOklch($b);

        $aa = [$ac * cos(deg2rad($ah)), $ac * sin(deg2rad($ah))];
        $bb = [$bc * cos(deg2rad($bh)), $bc * sin(deg2rad($bh))];

        $mixA = $aa[0] * $weight + $bb[0] * (1 - $weight);
        $mixB = $aa[1] * $weight + $bb[1] * (1 - $weight);

        return self::fromOklch(
            $al * $weight + $bl * (1 - $weight),
            sqrt($mixA ** 2 + $mixB ** 2),
            rad2deg(atan2($mixB, $mixA)),
        );
    }

    /** WCAG 2.2 relative-luminance contrast ratio, 1:1 to 21:1. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(self::linearise(...), self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** True when the string is a `#rgb` or `#rrggbb` colour. */
    public static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1;
    }

    /** Lower-cased `#rrggbb`, expanding the shorthand form. */
    public static function normalise(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }

    /* ------------------------------------------------------------ internals */

    /** @return array{float, float, float} r, g, b in 0–1 */
    private static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalise($hex), '#');

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private static function linearise(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function compand(float $channel): float
    {
        return $channel <= 0.0031308 ? $channel * 12.92 : 1.055 * $channel ** (1 / 2.4) - 0.055;
    }

    /** @return array{float, float, float} L 0–1, C, H in degrees */
    private static function toOklch(string $hex): array
    {
        [$r, $g, $b] = array_map(self::linearise(...), self::rgb($hex));

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        $okL = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $okA = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $okB = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return [$okL, sqrt($okA ** 2 + $okB ** 2), rad2deg(atan2($okB, $okA))];
    }

    /**
     * OKLCH back to `#rrggbb`, reducing chroma until the colour fits inside
     * sRGB. Clamping the channels instead would shift the hue — the one thing
     * the ramp must never do.
     */
    private static function fromOklch(float $okL, float $okC, float $okH): string
    {
        $okL = max(0.0, min(1.0, $okL));

        for ($i = 0; $i <= 40; $i++) {
            $rgb = self::oklchToRgb($okL, $okC * (1 - $i / 40), $okH);

            if ($rgb !== null) {
                return self::hex($rgb);
            }
        }

        return self::hex([$okL, $okL, $okL]);
    }

    /** @return array{float, float, float}|null null when outside sRGB */
    private static function oklchToRgb(float $okL, float $okC, float $okH): ?array
    {
        $a = $okC * cos(deg2rad($okH));
        $b = $okC * sin(deg2rad($okH));

        $l = ($okL + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($okL - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($okL - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $rgb = [
            self::compand(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
            self::compand(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
            self::compand(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s),
        ];

        foreach ($rgb as $channel) {
            // A hair of tolerance, so rounding at the very edge of the gamut
            // does not cost a whole chroma step.
            if ($channel < -0.001 || $channel > 1.001) {
                return null;
            }
        }

        return $rgb;
    }

    /** @param array{float, float, float} $rgb */
    private static function hex(array $rgb): string
    {
        return '#'.implode('', array_map(
            fn (float $channel) => str_pad(dechex((int) round(max(0.0, min(1.0, $channel)) * 255)), 2, '0', STR_PAD_LEFT),
            $rgb,
        ));
    }
}
