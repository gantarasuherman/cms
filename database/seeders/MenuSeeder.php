<?php

namespace Database\Seeders;

use App\Models\AdminMenu;
use App\Models\Menu;
use App\Models\PublicMenu;
use App\Services\Menu\MenuService;
use Illuminate\Database\Seeder;

/**
 * Seeds the *default* navigation. Both trees are ordinary rows after this:
 * administrators rename, reorder, nest, hide or delete them from
 * /admin/menus without touching code.
 *
 * Re-running is safe: rows are matched by slug and updated, never duplicated.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(AdminMenu::class, $this->adminMenu());
        $this->seed(PublicMenu::class, $this->publicMenu());

        app(MenuService::class)->forget();
    }

    /**
     * @param  class-string<Menu>  $model
     * @param  array<int, array<string, mixed>>  $items
     */
    private function seed(string $model, array $items, ?int $parentId = null): void
    {
        foreach ($items as $index => $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);

            /** @var Menu $menu */
            $menu = $model::withoutGlobalScopes()->firstOrNew(['slug' => $item['slug']]);
            $menu->fill($item + [
                'parent_id' => $parentId,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'target' => '_self',
            ]);
            $menu->save();

            if ($children !== []) {
                $this->seed($model, $children, $menu->getKey());
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function adminMenu(): array
    {
        return [
            ['slug' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard'],

            ['slug' => 'content', 'title' => 'Konten', 'icon' => 'layers', 'children' => [
                ['slug' => 'news', 'title' => 'Berita', 'icon' => 'newspaper', 'children' => [
                    ['slug' => 'news-all', 'title' => 'Semua Berita', 'icon' => 'list', 'route' => 'admin.news.index', 'permission' => 'news.view'],
                    ['slug' => 'news-category', 'title' => 'Kategori', 'icon' => 'folder-tree', 'route' => 'admin.news.category.index', 'permission' => 'category.view'],
                    ['slug' => 'news-tag', 'title' => 'Tag', 'icon' => 'tag', 'route' => 'admin.news.tag.index', 'permission' => 'tag.view'],
                ]],
                ['slug' => 'pages', 'title' => 'Halaman', 'icon' => 'file-text', 'route' => 'admin.pages.index', 'permission' => 'page.view'],
                ['slug' => 'services', 'title' => 'Layanan', 'icon' => 'briefcase', 'children' => [
                    ['slug' => 'services-all', 'title' => 'Semua Layanan', 'icon' => 'list', 'route' => 'admin.services.index', 'permission' => 'service.view'],
                    ['slug' => 'services-category', 'title' => 'Kategori', 'icon' => 'folder-tree', 'route' => 'admin.services.category.index', 'permission' => 'category.view'],
                ]],
                ['slug' => 'documents', 'title' => 'Dokumen', 'icon' => 'files', 'children' => [
                    ['slug' => 'documents-all', 'title' => 'Semua Dokumen', 'icon' => 'list', 'route' => 'admin.documents.index', 'permission' => 'document.view'],
                    ['slug' => 'documents-category', 'title' => 'Kategori', 'icon' => 'folder-tree', 'route' => 'admin.documents.category.index', 'permission' => 'category.view'],
                ]],
                ['slug' => 'faq', 'title' => 'FAQ', 'icon' => 'circle-help', 'children' => [
                    ['slug' => 'faq-all', 'title' => 'Semua FAQ', 'icon' => 'list', 'route' => 'admin.faq.index', 'permission' => 'faq.view'],
                    ['slug' => 'faq-category', 'title' => 'Kategori', 'icon' => 'folder-tree', 'route' => 'admin.faq.category.index', 'permission' => 'category.view'],
                ]],
                ['slug' => 'media', 'title' => 'Media', 'icon' => 'images', 'route' => 'admin.media.index', 'permission' => 'media.view'],
            ]],

            ['slug' => 'chatbot', 'title' => 'Chatbot', 'icon' => 'workflow', 'children' => [
                ['slug' => 'complaints', 'title' => 'Pengaduan', 'icon' => 'clipboard-list', 'route' => 'admin.complaints.index', 'permission' => 'complaint.view'],
                ['slug' => 'complaints-map', 'title' => 'Peta Pengaduan', 'icon' => 'map-pin', 'route' => 'admin.complaints.map', 'permission' => 'complaint.view'],
                ['slug' => 'complaint-categories', 'title' => 'Jenis Pengaduan', 'icon' => 'folder-tree', 'route' => 'admin.complaint-categories.index', 'permission' => 'chatbot.view'],
                ['slug' => 'dispositions', 'title' => 'Tujuan Pengarahan', 'icon' => 'send', 'route' => 'admin.dispositions.index', 'permission' => 'complaint.view'],
                ['slug' => 'bot-channels', 'title' => 'Pengaturan Chatbot', 'icon' => 'settings', 'route' => 'admin.bot.channels.index', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-ai', 'title' => 'Bantuan AI', 'icon' => 'sparkles', 'route' => 'admin.bot.ai.edit', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-flows', 'title' => 'Alur Percakapan', 'icon' => 'workflow', 'route' => 'admin.bot.flows.index', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-data-sources', 'title' => 'Sumber Data', 'icon' => 'database', 'route' => 'admin.bot.data-sources.index', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-recipients', 'title' => 'Petugas Penerima', 'icon' => 'users', 'route' => 'admin.bot.recipients.index', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-simulator', 'title' => 'Coba Percakapan', 'icon' => 'message-circle', 'route' => 'admin.bot.simulator.index', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-conversations', 'title' => 'Riwayat Percakapan', 'icon' => 'send', 'route' => 'admin.bot.conversations.index', 'permission' => 'chatbot.view'],
                ['slug' => 'bot-questions', 'title' => 'Pertanyaan Sering Muncul', 'icon' => 'circle-help', 'route' => 'admin.bot.questions.index', 'permission' => 'faq.view'],
            ]],

            ['slug' => 'website', 'title' => 'Website', 'icon' => 'globe', 'children' => [
                ['slug' => 'menus', 'title' => 'Menu', 'icon' => 'list', 'children' => [
                    ['slug' => 'menus-admin', 'title' => 'Menu Admin', 'icon' => 'panels-top-left', 'route' => 'admin.menus.admin.index', 'permission' => 'menu.view'],
                    ['slug' => 'menus-public', 'title' => 'Menu Publik', 'icon' => 'globe', 'route' => 'admin.menus.public.index', 'permission' => 'menu.view'],
                ]],
                ['slug' => 'announcements', 'title' => 'Pengumuman', 'icon' => 'megaphone', 'route' => 'admin.announcements.index', 'permission' => 'announcement.view'],
                ['slug' => 'social-posts', 'title' => 'Unggahan Sosial', 'icon' => 'image', 'route' => 'admin.social-posts.index', 'permission' => 'social_post.view'],
                ['slug' => 'settings-homepage', 'title' => 'Beranda', 'icon' => 'house', 'route' => 'admin.settings.homepage.index', 'permission' => 'settings.view'],
                ['slug' => 'settings-carousel', 'title' => 'Carousel', 'icon' => 'images', 'route' => 'admin.settings.carousel.index', 'permission' => 'settings.view'],
                ['slug' => 'settings-appearance', 'title' => 'Tampilan', 'icon' => 'palette', 'route' => 'admin.settings.appearance.edit', 'permission' => 'settings.view'],
                ['slug' => 'settings-accessibility', 'title' => 'Aksesibilitas', 'icon' => 'accessibility', 'route' => 'admin.settings.accessibility.edit', 'permission' => 'settings.view'],
                ['slug' => 'settings-seo', 'title' => 'SEO', 'icon' => 'search', 'route' => 'admin.settings.seo.edit', 'permission' => 'settings.view'],
                ['slug' => 'settings-social', 'title' => 'Media Sosial', 'icon' => 'link', 'route' => 'admin.settings.social.index', 'permission' => 'settings.view'],
                ['slug' => 'settings-footer', 'title' => 'Footer', 'icon' => 'panels-top-left', 'route' => 'admin.settings.footer.edit', 'permission' => 'settings.view'],
                ['slug' => 'settings-general', 'title' => 'Pengaturan Umum', 'icon' => 'settings', 'route' => 'admin.settings.general.edit', 'permission' => 'settings.view'],
            ]],

            ['slug' => 'access', 'title' => 'Pengguna & Akses', 'icon' => 'shield', 'children' => [
                ['slug' => 'users', 'title' => 'Pengguna', 'icon' => 'users', 'route' => 'admin.users.index', 'permission' => 'user.view'],
                ['slug' => 'roles', 'title' => 'Peran', 'icon' => 'shield-check', 'route' => 'admin.roles.index', 'permission' => 'role.view'],
                ['slug' => 'permissions', 'title' => 'Hak Akses', 'icon' => 'key-round', 'route' => 'admin.permissions.index', 'permission' => 'role.view'],
            ]],

            ['slug' => 'system', 'title' => 'Sistem', 'icon' => 'server', 'children' => [
                ['slug' => 'audit-logs', 'title' => 'Audit Log', 'icon' => 'history', 'route' => 'admin.audit-logs.index', 'permission' => 'audit_log.view'],
            ]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function publicMenu(): array
    {
        return [
            ['slug' => 'beranda', 'title' => 'Beranda', 'icon' => 'house', 'route' => 'public.home'],
            ['slug' => 'profil', 'title' => 'Profil', 'icon' => 'building-2', 'url' => '/halaman/profil'],
            // Nested so a fresh install demonstrates the multi-level menu the
            // navigation supports. Every child points at a filter the news
            // index already honours, so none of them is a dead link.
            ['slug' => 'berita', 'title' => 'Berita', 'icon' => 'newspaper', 'route' => 'public.news.index', 'children' => [
                ['slug' => 'berita-semua', 'title' => 'Semua Berita', 'icon' => 'newspaper', 'route' => 'public.news.index'],
                ['slug' => 'berita-infrastruktur', 'title' => 'Infrastruktur', 'icon' => 'building-2', 'url' => '/berita?kategori=infrastruktur'],
                ['slug' => 'berita-kegiatan', 'title' => 'Kegiatan', 'icon' => 'calendar', 'url' => '/berita?kategori=kegiatan'],
                ['slug' => 'berita-pengumuman', 'title' => 'Pengumuman', 'icon' => 'megaphone', 'url' => '/berita?kategori=pengumuman'],
            ]],
            ['slug' => 'layanan', 'title' => 'Layanan', 'icon' => 'briefcase', 'route' => 'public.services.index'],
            ['slug' => 'dokumen', 'title' => 'Dokumen', 'icon' => 'files', 'route' => 'public.documents.index'],
            ['slug' => 'faq', 'title' => 'FAQ', 'icon' => 'circle-help', 'route' => 'public.faq.index'],
            ['slug' => 'kontak', 'title' => 'Kontak', 'icon' => 'phone', 'url' => '/halaman/kontak'],
        ];
    }
}

