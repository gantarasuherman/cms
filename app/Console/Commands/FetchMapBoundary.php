<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Mengambil batas wilayah untuk Peta Pengaduan.
 *
 * Dijadikan perintah, bukan berkas yang sekadar ada di repositori, supaya
 * jelas dari mana angkanya berasal dan dapat diambil ulang bila wilayahnya
 * berubah. Sebuah poligon batas yang muncul entah dari mana pada peta
 * pemerintah adalah klaim yang tidak bisa diperiksa siapa pun.
 *
 * Hasilnya disimpan sebagai berkas statis, bukan disisipkan ke HTML halaman:
 * batasnya ratusan kilobyte dan tidak pernah berubah, jadi peramban cukup
 * mengunduhnya sekali lalu menyinggahkannya.
 */
class FetchMapBoundary extends Command
{
    protected $signature = 'peta:batas
        {wilayah=Kabupaten Indramayu : Nama wilayah seperti dikenal OpenStreetMap}';

    protected $description = 'Mengambil batas wilayah dari OpenStreetMap untuk Peta Pengaduan';

    public function handle(): int
    {
        $area = (string) $this->argument('wilayah');

        $this->info("Mencari batas \"{$area}\" di OpenStreetMap…");

        try {
            $response = Http::timeout(60)
                // Kebijakan Nominatim meminta agen yang dapat dikenali.
                ->withHeaders(['User-Agent' => config('app.name').' boundary fetch (self-hosted)'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $area,
                    'format' => 'json',
                    'polygon_geojson' => 1,
                    'limit' => 1,
                ]);
        } catch (\Throwable $e) {
            $this->error('Tidak dapat menghubungi OpenStreetMap: '.$e->getMessage());

            return self::FAILURE;
        }

        $result = $response->json('0');

        if (! $result || empty($result['geojson'])) {
            $this->error("Wilayah \"{$area}\" tidak ditemukan.");

            return self::FAILURE;
        }

        // Disimpan sebagai Feature lengkap dengan namanya, bukan geometri
        // telanjang: enam bulan dari sekarang, berkas berisi ribuan koordinat
        // tanpa keterangan tidak dapat dipastikan menggambarkan wilayah apa.
        $feature = [
            'type' => 'Feature',
            'properties' => [
                'name' => $result['display_name'] ?? $area,
                'source' => 'OpenStreetMap / Nominatim',
                'fetched_at' => now()->toIso8601String(),
            ],
            'geometry' => $result['geojson'],
        ];

        Storage::disk('public')->put(
            config('complaints.boundary_path'),
            json_encode($feature, JSON_UNESCAPED_SLASHES),
        );

        $this->info('Tersimpan: '.$result['display_name']);
        $this->line('  tipe  : '.$result['geojson']['type']);
        $this->line('  batas : '.implode(', ', $result['boundingbox'] ?? []));

        return self::SUCCESS;
    }
}
