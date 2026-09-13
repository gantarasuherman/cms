<?php

namespace Database\Seeders;

use App\Models\SocialPost;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Example posts, so the homepage section is not an empty grid on a fresh
 * install. Matched by permalink, so re-running never duplicates them, and a
 * placeholder is only drawn when its file is missing — a real screenshot an
 * administrator has uploaded is never overwritten.
 */
class SocialPostSeeder extends Seeder
{
    public function run(): void
    {
        // Engagement figures are examples too. They are what an administrator
        // would copy from their own post; nothing fetches them.
        $examples = [
            ['instagram', 'Peninjauan progres pembangunan jalan penghubung antar desa. Target rampung akhir tahun ini.', 'https://www.instagram.com/', '#02468B', 1284, 96],
            ['instagram', 'Kegiatan bersih sungai bersama warga dan komunitas peduli lingkungan.', 'https://www.instagram.com/explore/', '#0b6bbd', 12400, 312],
            ['facebook', 'Sosialisasi layanan perizinan daring di kantor kecamatan.', 'https://www.facebook.com/', '#EF8519', 486, 24],
            ['x', 'Info lalu lintas: perbaikan jembatan dimulai pekan depan. Harap gunakan jalur alternatif.', 'https://x.com/', '#0F172A', null, null],
        ];

        foreach ($examples as $index => [$platform, $caption, $permalink, $colour, $likes, $comments]) {
            $path = 'social-posts/contoh-'.($index + 1).'.png';

            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, $this->placeholder($colour));
            }

            SocialPost::firstOrCreate(['permalink' => $permalink], [
                'platform' => $platform,
                'account_handle' => 'dinaspupr',
                'likes' => $likes,
                'comments' => $comments,
                'image' => $path,
                'alt_text' => null,
                'caption' => $caption,
                'posted_at' => now()->subDays(($index + 1) * 3),
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }

        app(PublicCache::class)->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);

        $this->command?->info('Unggahan sosial: '.count($examples).' tersimpan.');
    }

    /** A flat 1080×1080 PNG, drawn without GD so the seeder has no extension requirement. */
    private function placeholder(string $hex): string
    {
        [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];

        $size = 1080;
        $raw = '';

        for ($y = 0; $y < $size; $y++) {
            $raw .= chr(0).str_repeat(chr($r).chr($g).chr($b), $size);
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNccccc', $size, $size, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw, 9))
            .$chunk('IEND', '');
    }
}
