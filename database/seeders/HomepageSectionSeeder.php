<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Seeder;

/**
 * A sensible default homepage layout. Every row here is editable, reorderable
 * and removable from /admin/settings/homepage — this only saves an
 * administrator from starting with a blank page.
 */
class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // The hero slider opens the page: it is the first thing a visitor
            // sees, which is the whole point of it.
            ['type' => 'carousel', 'title' => 'Sorotan Utama'],
            ['type' => 'hero', 'title' => 'Portal Informasi dan Layanan Publik', 'subtitle' => 'Berita, layanan, dan dokumen resmi dalam satu tempat.'],
            ['type' => 'featured_news', 'title' => 'Berita Utama', 'settings' => ['limit' => 3]],
            ['type' => 'services', 'title' => 'Layanan', 'subtitle' => 'Persyaratan, tarif, dan waktu penyelesaian.', 'settings' => ['limit' => 6]],
            ['type' => 'latest_news', 'title' => 'Berita Terbaru', 'settings' => ['limit' => 6]],
            ['type' => 'documents', 'title' => 'Dokumen Terbaru', 'settings' => ['limit' => 6]],
            ['type' => 'statistics', 'title' => 'Dalam Angka'],
            ['type' => 'social_posts', 'title' => 'Ikuti Kami', 'subtitle' => 'Kegiatan terbaru dari akun resmi kami.', 'settings' => ['limit' => 8]],
            ['type' => 'faq', 'title' => 'Pertanyaan yang Sering Diajukan', 'settings' => ['limit' => 5]],
        ];

        foreach ($defaults as $index => $section) {
            // Matched by type so re-running never duplicates the layout.
            HomepageSection::firstOrCreate(
                ['type' => $section['type']],
                [
                    'title' => $section['title'] ?? null,
                    'subtitle' => $section['subtitle'] ?? null,
                    'settings' => $section['settings'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }

        app(PublicCache::class)->forget(PublicCache::HOMEPAGE);
    }
}

