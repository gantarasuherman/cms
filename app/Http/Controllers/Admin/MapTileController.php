<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Map tiles, fetched by this server and passed on.
 *
 * The map on the analysis screen is a real one — it pans and zooms — but the
 * operator's browser still never speaks to a tile provider. Every tile is
 * requested here, cached here, and served from here, so OpenStreetMap sees one
 * server asking for a district's tiles rather than every officer's address
 * paired with the coordinates of the complaints they are looking at.
 *
 * The cache is what makes this affordable and what keeps the project inside
 * OpenStreetMap's tile policy: a tile is fetched once and then served from
 * disk however many times it is panned over.
 */
class MapTileController extends Controller
{
    private const DISK = 'public';

    private const DIRECTORY = 'maps/tiles';

    /** OpenStreetMap serves no deeper than this. */
    private const MAX_ZOOM = 19;

    public function __invoke(Request $request, int $z, int $x, int $y): Response
    {
        // Only somebody who may read complaints may use the complaint map.
        $this->authorize('viewAny', Complaint::class);

        // Bounds first: these three numbers are about to become a URL and a
        // file path, and nothing else validates them.
        $max = 2 ** $z;

        if ($z < 0 || $z > self::MAX_ZOOM || $x < 0 || $y < 0 || $x >= $max || $y >= $max) {
            abort(404);
        }

        $path = sprintf('%s/%d/%d/%d.png', self::DIRECTORY, $z, $x, $y);
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            $bytes = $this->fetch($z, $x, $y);

            if ($bytes === null) {
                // A transparent pixel: one unreachable tile leaves a gap in
                // the map rather than a broken-image icon across it.
                return response($this->blank(), 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'no-store',
                ]);
            }

            // Gagal menyimpan bukan alasan untuk membuang petak yang sudah
            // berhasil diambil.
            //
            // Sebelumnya `put()` yang melempar UnableToCreateDirectory — izin
            // tulis yang keliru pada storage — menjatuhkan seluruh permintaan,
            // dan petanya tampil kosong dengan titik-titik mengambang di atas
            // latar abu-abu. Petaknya ada di tangan; yang gagal hanya
            // menyimpannya untuk lain kali.
            try {
                $disk->put($path, $bytes);
            } catch (\Throwable $e) {
                Log::warning('Petak peta tidak dapat disimpan; disajikan tanpa cache.', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);

                return $this->image($bytes);
            }
        }

        return $this->image($disk->get($path));
    }

    /**
     * Satu petak beserta aturan singgahnya.
     *
     * ETag-nya jalur petak itu sendiri: sebuah petak pada z/x/y yang sama
     * tidak pernah berubah isinya, jadi peramban yang sudah memilikinya cukup
     * menerima 304 dan tidak mengunduh apa pun. Inilah yang membuat menggeser
     * peta bolak-balik hampir tidak berbiaya.
     */
    private function image(string $bytes): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            // Long, because a tile at a given z/x/y never changes meaningfully
            // and this is what keeps the server's own fetch count down.
            'Cache-Control' => 'private, max-age=604800, immutable',
            'ETag' => '"'.md5($bytes).'"',
        ]);
    }

    private function fetch(int $z, int $x, int $y): ?string
    {
        try {
            $response = Http::timeout(8)
                // OpenStreetMap's tile policy asks for an identifying agent
                // and refuses anonymous bulk clients.
                ->withHeaders(['User-Agent' => config('app.name').' CMS map proxy (self-hosted, cached)'])
                ->get("https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png");
        } catch (\Throwable $e) {
            Log::warning('Petak peta gagal diambil', ['message' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    private function blank(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }
}
