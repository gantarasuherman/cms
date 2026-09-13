<?php

namespace Database\Seeders;

use App\Models\CarouselSlide;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Starter slides for the hero.
 *
 * Everything here is an ordinary row afterwards: an administrator edits,
 * reorders, schedules or removes them from /admin/settings/carousel. Re-running
 * is safe — slides are matched by title and updated, never duplicated — and the
 * placeholder artwork is only generated when the file is missing, so a real
 * photograph uploaded later is never overwritten.
 */
class CarouselSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->slides() as $index => $slide) {
            $path = 'carousel/seed-'.($index + 1).'.jpg';

            if (! Storage::disk('public')->exists($path)) {
                $this->generatePlaceholder($path, $slide['tint'], $slide['category']);
            }

            CarouselSlide::updateOrCreate(
                ['title' => $slide['title']],
                [
                    'category' => $slide['category'],
                    'description' => $slide['description'],
                    'button_text' => $slide['button_text'],
                    'link' => $slide['link'],
                    'alt_text' => $slide['alt_text'],
                    'image' => $path,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }

        app(PublicCache::class)->forget(PublicCache::CAROUSEL, PublicCache::HOMEPAGE);

        $this->command?->info('Hero slider: '.count($this->slides()).' slide tersimpan.');
    }

    /** @return array<int, array<string, mixed>> */
    private function slides(): array
    {
        return [
            [
                'category' => 'Infrastruktur',
                'title' => 'Infrastruktur Terus Dibenahi',
                'description' => 'Pemerintah Kabupaten terus mendorong peningkatan infrastruktur secara bertahap sesuai skala prioritas dan kondisi lapangan.',
                'button_text' => 'Lihat Selengkapnya',
                'link' => '/berita',
                'alt_text' => 'Pekerja meninjau pembangunan infrastruktur di lapangan',
                'tint' => [2, 70, 139],
            ],
            [
                'category' => 'Jalan',
                'title' => 'Pemeliharaan Jalan untuk Mobilitas Masyarakat',
                'description' => 'Penanganan ruas jalan dilakukan untuk mendukung konektivitas dan aktivitas masyarakat.',
                'button_text' => 'Lihat Kegiatan',
                'link' => '/berita',
                'alt_text' => 'Ruas jalan kabupaten yang sedang diperbaiki',
                'tint' => [17, 94, 89],
            ],
            [
                'category' => 'Irigasi',
                'title' => 'Menjaga Infrastruktur Irigasi',
                'description' => 'Pemeliharaan dan penanganan jaringan irigasi mendukung kelancaran pengelolaan sumber daya air.',
                'button_text' => 'Lihat Informasi',
                'link' => '/berita',
                'alt_text' => 'Saluran irigasi yang mengalirkan air ke area persawahan',
                'tint' => [12, 74, 110],
            ],
            [
                'category' => 'Pelayanan Publik',
                'title' => 'Informasi dan Layanan untuk Masyarakat',
                'description' => 'Akses informasi pembangunan dan pelayanan publik Pemerintah Kabupaten dengan mudah.',
                'button_text' => 'Lihat Layanan',
                'link' => '/layanan',
                'alt_text' => 'Petugas melayani warga di loket pelayanan publik',
                'tint' => [30, 41, 59],
            ],
        ];
    }

    /**
     * A stand-in image so the hero is demonstrable before real photographs are
     * uploaded. Landscape at 1920x1080 so the layout is exercised at the size
     * it will actually receive.
     */
    private function generatePlaceholder(string $path, array $tint, string $label): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        [$width, $height] = [1920, 1080];
        $image = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = $tint;

        for ($y = 0; $y < $height; $y++) {
            $fade = 1 - ($y / $height) * 0.55;
            $colour = imagecolorallocate(
                $image,
                min(255, (int) ($r * $fade) + 30),
                min(255, (int) ($g * $fade) + 40),
                min(255, (int) ($b * $fade) + 55),
            );
            imageline($image, 0, $y, $width, $y, $colour);
        }

        $white = imagecolorallocatealpha($image, 255, 255, 255, 100);
        for ($i = 0; $i < 5; $i++) {
            imagefilledellipse($image, 1500 + $i * 90, 300 + $i * 110, 420, 420, $white);
        }

        $ink = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 1500, 1020, 'Foto: '.$label, $ink);

        Storage::disk('public')->makeDirectory('carousel');
        imagejpeg($image, Storage::disk('public')->path($path), 82);
        imagedestroy($image);
    }
}

