<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A small map picture, assembled on this server.
 *
 * The tiles are fetched here and stitched here, so an operator's browser never
 * calls a map provider. An embedded map — or even a hot-linked static image —
 * would hand that provider the coordinates of somebody's complaint and the
 * operator's address, on every page view, for every complaint they open. The
 * project already downloads social images rather than hot-linking them and
 * hosts its own fonts; this is the same rule.
 *
 * Each picture is kept, so a complaint opened a hundred times costs one fetch.
 */
class MapSnapshot
{
    private const TILE = 256;

    private const ZOOM = 16;

    /** Half a tile of margin each way, then cropped to this. */
    private const WIDTH = 384;

    private const HEIGHT = 224;

    private const DISK = 'public';

    private const DIRECTORY = 'maps';

    /**
     * Returns a path on the public disk, or null when a map could not be made.
     *
     * Null is an ordinary outcome — no network, a provider refusing, GD
     * missing — and every caller falls back to printing the coordinates.
     */
    public function for(float $latitude, float $longitude): ?string
    {
        // Rounded to about 10 metres: two complaints on the same corner share
        // one picture, and the filename gives nothing away that the map does
        // not already show.
        $key = sprintf('%s/%.4f_%.4f.png', self::DIRECTORY, $latitude, $longitude);

        if (Storage::disk(self::DISK)->exists($key)) {
            return $key;
        }

        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = $this->render($latitude, $longitude);

        if ($image === null) {
            return null;
        }

        Storage::disk(self::DISK)->put($key, $image);

        return $key;
    }

    public function url(float $latitude, float $longitude): ?string
    {
        $path = $this->for($latitude, $longitude);

        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    private function render(float $latitude, float $longitude): ?string
    {
        [$x, $y] = $this->pixelOf($latitude, $longitude);

        // The tile the point sits in, plus one each way, so the crop below
        // always has real map under it rather than blank edges.
        $originTileX = (int) floor($x / self::TILE) - 1;
        $originTileY = (int) floor($y / self::TILE) - 1;

        $canvas = imagecreatetruecolor(self::TILE * 3, self::TILE * 3);

        for ($dx = 0; $dx < 3; $dx++) {
            for ($dy = 0; $dy < 3; $dy++) {
                $tile = $this->tile($originTileX + $dx, $originTileY + $dy);

                if ($tile === null) {
                    imagedestroy($canvas);

                    return null;
                }

                imagecopy($canvas, $tile, $dx * self::TILE, $dy * self::TILE, 0, 0, self::TILE, self::TILE);
                imagedestroy($tile);
            }
        }

        // Where the point landed inside the nine-tile canvas.
        $centreX = (int) round($x - $originTileX * self::TILE);
        $centreY = (int) round($y - $originTileY * self::TILE);

        $out = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopy(
            $out, $canvas,
            0, 0,
            (int) max(0, $centreX - self::WIDTH / 2),
            (int) max(0, $centreY - self::HEIGHT / 2),
            self::WIDTH, self::HEIGHT,
        );
        imagedestroy($canvas);

        $this->drawMarker($out, (int) (self::WIDTH / 2), (int) (self::HEIGHT / 2));

        ob_start();
        imagepng($out, null, 8);
        $bytes = (string) ob_get_clean();
        imagedestroy($out);

        return $bytes;
    }

    /** A pin, drawn rather than downloaded, so the picture needs nothing else. */
    private function drawMarker(\GdImage $image, int $x, int $y): void
    {
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 90);
        $body = imagecolorallocate($image, 220, 38, 38);
        $ring = imagecolorallocate($image, 255, 255, 255);

        imagefilledellipse($image, $x, $y + 9, 16, 6, $shadow);

        // A teardrop: a circle with a triangle beneath it.
        imagefilledpolygon($image, [$x - 6, $y - 4, $x + 6, $y - 4, $x, $y + 8], $body);
        imagefilledellipse($image, $x, $y - 8, 20, 20, $ring);
        imagefilledellipse($image, $x, $y - 8, 14, 14, $body);
    }

    private function tile(int $x, int $y): ?\GdImage
    {
        $max = 2 ** self::ZOOM;

        if ($x < 0 || $y < 0 || $x >= $max || $y >= $max) {
            return imagecreatetruecolor(self::TILE, self::TILE);
        }

        try {
            $response = Http::timeout(8)
                // OpenStreetMap's tile policy asks for an identifying agent and
                // refuses anonymous bulk clients.
                ->withHeaders(['User-Agent' => config('app.name').' CMS map snapshot (self-hosted, cached)'])
                ->get("https://tile.openstreetmap.org/".self::ZOOM."/{$x}/{$y}.png");
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

    /** @return array{float, float} pixel position at this zoom */
    private function pixelOf(float $latitude, float $longitude): array
    {
        $n = self::TILE * 2 ** self::ZOOM;
        $lat = deg2rad(max(-85.05112878, min(85.05112878, $latitude)));

        return [
            ($longitude + 180) / 360 * $n,
            (1 - log(tan($lat) + 1 / cos($lat)) / M_PI) / 2 * $n,
        ];
    }
}
