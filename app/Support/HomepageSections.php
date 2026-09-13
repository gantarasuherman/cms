<?php

namespace App\Support;

/**
 * The section types the public homepage knows how to render.
 *
 * Which sections exist, their wording, their order and whether they are shown
 * are all database rows an administrator controls. What is fixed here is the
 * set of *renderers* the code provides — offering a type with no renderer
 * would put an invisible section on the page.
 */
final class HomepageSections
{
    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            'hero' => 'Hero — judul besar dan ajakan bertindak',
            'carousel' => 'Carousel — slide bergambar',
            'featured_news' => 'Berita Utama',
            'latest_news' => 'Berita Terbaru',
            'services' => 'Layanan',
            'documents' => 'Dokumen',
            'faq' => 'FAQ',
            'statistics' => 'Statistik',
            'social_posts' => 'Unggahan Media Sosial',
            'banner' => 'Banner',
            'custom' => 'Konten Bebas',
        ];
    }

    /** Section types whose item count an administrator can cap. */
    public static function supportsLimit(string $type): bool
    {
        return in_array($type, ['featured_news', 'latest_news', 'services', 'documents', 'faq', 'social_posts'], true);
    }
}

