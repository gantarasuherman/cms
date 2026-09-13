<?php

namespace App\Services\Maps;

/**
 * The pins drawn on a map, drawn rather than downloaded so a picture needs
 * nothing but the tiles under it.
 */
final class MapMarker
{
    /** A single teardrop: a circle with a triangle beneath it. */
    public static function pin(\GdImage $image, int $x, int $y): void
    {
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 90);
        $body = imagecolorallocate($image, 220, 38, 38);
        $ring = imagecolorallocate($image, 255, 255, 255);

        imagefilledellipse($image, $x, $y + 9, 16, 6, $shadow);
        imagefilledpolygon($image, [$x - 6, $y - 4, $x + 6, $y - 4, $x, $y + 8], $body);
        imagefilledellipse($image, $x, $y - 8, 20, 20, $ring);
        imagefilledellipse($image, $x, $y - 8, 14, 14, $body);
    }

    /**
     * A cluster: one disc carrying how many complaints are inside it.
     *
     * Size follows the count so a glance ranks the trouble spots, and the
     * number is drawn as well — area is a poor quantity to read, and a
     * colour-blind or low-vision operator must not be left with only the
     * size to go on (WCAG 1.4.1).
     */
    public static function cluster(\GdImage $image, int $x, int $y, int $count, int $rank): void
    {
        $radius = (int) min(34, 14 + $count * 3);

        // Rank, not count: the busiest spot stays red however many there are,
        // so two screens with different totals still read the same way.
        [$r, $g, $b] = match (true) {
            $rank === 0 => [220, 38, 38],
            $rank === 1 => [234, 88, 12],
            $rank === 2 => [202, 138, 4],
            default => [37, 99, 235],
        };

        $halo = imagecolorallocatealpha($image, $r, $g, $b, 95);
        $body = imagecolorallocate($image, $r, $g, $b);
        $ring = imagecolorallocate($image, 255, 255, 255);

        imagefilledellipse($image, $x, $y, $radius * 2 + 14, $radius * 2 + 14, $halo);
        imagefilledellipse($image, $x, $y, $radius * 2, $radius * 2, $ring);
        imagefilledellipse($image, $x, $y, $radius * 2 - 6, $radius * 2 - 6, $body);

        $label = (string) $count;
        $font = $radius >= 22 ? 5 : 4;
        imagestring(
            $image, $font,
            $x - (int) (imagefontwidth($font) * strlen($label) / 2),
            $y - (int) (imagefontheight($font) / 2),
            $label,
            $ring,
        );
    }
}
