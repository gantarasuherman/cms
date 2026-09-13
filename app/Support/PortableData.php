<?php

namespace App\Support;

/**
 * What travels when a site is moved to another machine.
 *
 * A `mysqldump` moves everything, which is the wrong thing: it carries login
 * accounts, audit trails, visitor statistics and chatbot conversations to a
 * machine that has no business holding them, and it carries secrets encrypted
 * under an APP_KEY the other machine does not have. So the set is declared
 * rather than discovered, and each table is here for a stated reason.
 *
 * The order is the order rows are written and re-inserted: a child never
 * precedes the parent it points at.
 */
final class PortableData
{
    /**
     * The site's shape: navigation, appearance, permissions, the chatbot's
     * flow. None of it is anybody's personal data, and all of it is what makes
     * a fresh install *this* site rather than a blank one.
     *
     * @return array<int, string>
     */
    public static function configuration(): array
    {
        return [
            'icons',
            'permissions',
            'roles',
            'role_has_permissions',
            'settings',
            'admin_menus',
            'public_menus',
            'homepage_sections',
            'carousel_slides',
            'categories',
            'news_categories',
            'tags',
            'complaint_categories',
            'social_links',
            'bot_data_sources',
            'bot_flows',
            'bot_nodes',
            'bot_edges',
        ];
    }

    /**
     * What editors wrote. Separate from configuration because moving a site's
     * design to a new machine and moving its articles are different jobs —
     * a staging server usually wants the first and not the second.
     *
     * @return array<int, string>
     */
    public static function content(): array
    {
        return [
            'pages',
            'news',
            'news_tags',
            'services',
            'service_requirements',
            'service_steps',
            'service_tariffs',
            'documents',
            'faqs',
            'announcements',
            'social_posts',
            'social_post_media',
            'media',
        ];
    }

    /**
     * Tables that must never leave this machine, and why.
     *
     * Nothing reads this list — the export only writes what the two lists
     * above name. It is here so the omissions are a documented decision
     * rather than something that looks forgotten.
     *
     * @return array<string, string>
     */
    public static function excluded(): array
    {
        return [
            'users' => 'Akun dan kata sandi. Dibuat ulang di mesin tujuan lewat AdminUserSeeder.',
            'model_has_roles' => 'Mengikuti akun.',
            'model_has_permissions' => 'Mengikuti akun.',
            'password_reset_tokens' => 'Token sekali pakai.',
            'sessions' => 'Sesi login yang sedang berjalan.',
            'audit_logs' => 'Jejak perbuatan orang di mesin ini.',
            'page_views' => 'Statistik kunjungan mesin ini.',
            'bot_channels' => 'Kunci WhatsApp dan Telegram, terenkripsi dengan APP_KEY mesin ini — tidak akan terbaca di mesin lain. Isi ulang di /admin/bot/channels.',
            'bot_contacts' => 'Nomor telepon dan nama pelapor.',
            'bot_conversations' => 'Percakapan pelapor.',
            'bot_messages' => 'Isi percakapan pelapor.',
            'bot_outbox' => 'Antrean kirim yang sedang berjalan.',
            'bot_webhook_events' => 'Jejak permintaan masuk.',
            'bot_flow_versions' => 'Riwayat penyuntingan alur di mesin ini.',
            'bot_recipients' => 'Nomor petugas penerima notifikasi.',
            'bot_recipient_categories' => 'Mengikuti penerima.',
            'complaints' => 'Aduan warga, termasuk lokasi dan lampirannya.',
            'complaint_updates' => 'Mengikuti aduan.',
            'complaint_attachments' => 'Mengikuti aduan.',
            'cache' => 'Dibangun ulang sendiri.',
            'cache_locks' => 'Dibangun ulang sendiri.',
            'jobs' => 'Antrean yang sedang berjalan.',
            'job_batches' => 'Antrean yang sedang berjalan.',
            'failed_jobs' => 'Kegagalan di mesin ini.',
            'migrations' => 'Ditentukan oleh `php artisan migrate`, bukan oleh salinan data.',
        ];
    }

    /**
     * Values that are encrypted with this machine's APP_KEY.
     *
     * They are exported as an empty value, not dropped: the row says the
     * setting exists and the admin screen keeps its field, and the message on
     * import tells whoever runs it which keys to fill in again.
     *
     * @return array<int, array{table: string, match: array<string, string>, column: string}>
     */
    public static function secrets(): array
    {
        return [
            ['table' => 'settings', 'match' => ['type' => 'encrypted'], 'column' => 'value'],
        ];
    }
}
