<?php

namespace App\Services\Maps;

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
    /** Street level: close enough that the road a complaint names is legible. */
    private const ZOOM = 16;

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

        $canvas = TileCanvas::covering([[$latitude, $longitude]], self::WIDTH, self::HEIGHT, self::ZOOM);

        if ($canvas === null) {
            return null;
        }

        [$x, $y] = $canvas->place($latitude, $longitude);
        MapMarker::pin($canvas->image(), $x, $y);

        Storage::disk(self::DISK)->put($key, $canvas->png());

        return $key;
    }

    public function url(float $latitude, float $longitude): ?string
    {
        $path = $this->for($latitude, $longitude);

        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }
}
