<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A stitched piece of map, assembled on this server.
 *
 * The tiles are fetched here and drawn here, so nobody's browser calls a map
 * provider. An embedded map — or even a hot-linked static image — would hand
 * that provider the coordinates of somebody's complaint and the operator's
 * address on every page view. The project already downloads social images
 * rather than hot-linking them and hosts its own fonts; this is the same rule.
 *
 * Shared by the single-point snapshot on a complaint and the many-point plot
 * on the analysis screen: the tile policy, the retry behaviour and the
 * projection are one implementation rather than two that drift.
 */
final class TileCanvas
{
    public const TILE = 256;

    /** Street level. Below 12 a city fits but nothing on it is legible. */
    private const MAX_ZOOM = 17;

    private const MIN_ZOOM = 5;

    private function __construct(
        public readonly int $zoom,
        private readonly \GdImage $image,
        private readonly float $originX,
        private readonly float $originY,
    ) {
    }

    /**
     * A canvas of the given size showing every point, at the closest zoom
     * that still fits them all.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  [lat, lng]
     */
    public static function covering(array $points, int $width, int $height, ?int $zoom = null, int $padding = 48): ?self
    {
        if ($points === [] || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $zoom ??= self::fit($points, $width - $padding * 2, $height - $padding * 2);

        // The centre of the points, in world pixels at this zoom.
        [$minX, $minY, $maxX, $maxY] = self::bounds($points, $zoom);
        $centreX = ($minX + $maxX) / 2;
        $centreY = ($minY + $maxY) / 2;

        $originX = $centreX - $width / 2;
        $originY = $centreY - $height / 2;

        $firstTileX = (int) floor($originX / self::TILE);
        $firstTileY = (int) floor($originY / self::TILE);
        $lastTileX = (int) floor(($originX + $width) / self::TILE);
        $lastTileY = (int) floor(($originY + $height) / self::TILE);

        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 233, 231, 225));

        for ($tx = $firstTileX; $tx <= $lastTileX; $tx++) {
            for ($ty = $firstTileY; $ty <= $lastTileY; $ty++) {
                $tile = self::tile($tx, $ty, $zoom);

                if ($tile === null) {
                    imagedestroy($image);

                    return null;
                }

                imagecopy(
                    $image, $tile,
                    (int) round($tx * self::TILE - $originX),
                    (int) round($ty * self::TILE - $originY),
                    0, 0, self::TILE, self::TILE,
                );
                imagedestroy($tile);
            }
        }

        return new self($zoom, $image, $originX, $originY);
    }

    /** Where a coordinate lands on this canvas. @return array{int, int} */
    public function place(float $latitude, float $longitude): array
    {
        [$x, $y] = self::project($latitude, $longitude, $this->zoom);

        return [(int) round($x - $this->originX), (int) round($y - $this->originY)];
    }

    public function image(): \GdImage
    {
        return $this->image;
    }

    public function png(): string
    {
        ob_start();
        imagepng($this->image, null, 8);
        $bytes = (string) ob_get_clean();
        imagedestroy($this->image);

        return $bytes;
    }

    /**
     * The closest zoom at which every point still fits the given box.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private static function fit(array $points, int $width, int $height): int
    {
        for ($zoom = self::MAX_ZOOM; $zoom > self::MIN_ZOOM; $zoom--) {
            [$minX, $minY, $maxX, $maxY] = self::bounds($points, $zoom);

            if (($maxX - $minX) <= $width && ($maxY - $minY) <= $height) {
                return $zoom;
            }
        }

        return self::MIN_ZOOM;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array{float, float, float, float}
     */
    private static function bounds(array $points, int $zoom): array
    {
        $xs = $ys = [];

        foreach ($points as [$latitude, $longitude]) {
            [$x, $y] = self::project($latitude, $longitude, $zoom);
            $xs[] = $x;
            $ys[] = $y;
        }

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /** Web Mercator, in pixels at this zoom. @return array{float, float} */
    public static function project(float $latitude, float $longitude, int $zoom): array
    {
        $n = self::TILE * 2 ** $zoom;
        $lat = deg2rad(max(-85.05112878, min(85.05112878, $latitude)));

        return [
            ($longitude + 180) / 360 * $n,
            (1 - log(tan($lat) + 1 / cos($lat)) / M_PI) / 2 * $n,
        ];
    }

    private static function tile(int $x, int $y, int $zoom): ?\GdImage
    {
        $max = 2 ** $zoom;

        if ($x < 0 || $y < 0 || $x >= $max || $y >= $max) {
            // Off the edge of the world: blank rather than a failed fetch.
            return imagecreatetruecolor(self::TILE, self::TILE);
        }

        try {
            $response = Http::timeout(8)
                // OpenStreetMap's tile policy asks for an identifying agent and
                // refuses anonymous bulk clients.
                ->withHeaders(['User-Agent' => config('app.name').' CMS map snapshot (self-hosted, cached)'])
                ->get("https://tile.openstreetmap.org/{$zoom}/{$x}/{$y}.png");
        } catch (\Throwable $e) {
            Log::warning('Petak peta gagal diambil', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $tile = @imagecreatefromstring($response->body());

        return $tile ?: null;
    }
}
