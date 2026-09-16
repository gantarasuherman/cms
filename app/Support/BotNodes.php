<?php

namespace App\Support;

/**
 * The node vocabulary the conversation engine knows how to run.
 *
 * Which nodes exist in a flow, what they say and where they lead are database
 * rows an administrator draws. What is fixed here is the set of *behaviours*
 * the engine implements — offering a node type nothing can execute would put a
 * dead end in someone's conversation.
 *
 * Same shape as HomepageSections and Permissions: a declared vocabulary that
 * the editor, the validator and the Python runtime all read from one place.
 */
final class BotNodes
{
    public const START = 'start';

    public const MESSAGE = 'message';

    public const MENU = 'menu';

    public const INPUT = 'input';

    public const DATA_SOURCE = 'data_source';

    public const ACTION = 'action';

    public const AI = 'ai';

    public const END = 'end';

    /**
     * @return array<string, array{label: string, icon: string, description: string, outputs: string}>
     */
    public static function types(): array
    {
        return [
            self::START => [
                'label' => 'Mulai',
                'icon' => 'play',
                'description' => 'Titik masuk percakapan. Tepat satu per alur.',
                'outputs' => 'single',
            ],
            self::MESSAGE => [
                'label' => 'Pesan',
                'icon' => 'send',
                'description' => 'Bot mengirim teks, lalu lanjut ke node berikutnya tanpa menunggu jawaban.',
                'outputs' => 'single',
            ],
            self::MENU => [
                'label' => 'Menu Pilihan',
                'icon' => 'list-ordered',
                'description' => 'Menawarkan pilihan bernomor. Setiap pilihan punya jalurnya sendiri.',
                'outputs' => 'per-option',
            ],
            self::INPUT => [
                'label' => 'Minta Masukan',
                'icon' => 'clipboard-list',
                'description' => 'Menunggu jawaban pengguna: teks, angka, gambar, lokasi, atau gabungan.',
                'outputs' => 'validated',
            ],
            self::DATA_SOURCE => [
                'label' => 'Ambil Data',
                'icon' => 'database',
                'description' => 'Menampilkan daftar bernomor dari isi situs (berita, layanan, dokumen), lalu detailnya bila dipilih.',
                'outputs' => 'validated',
            ],
            self::ACTION => [
                'label' => 'Aksi',
                'icon' => 'workflow',
                'description' => 'Melakukan sesuatu: membuat pengaduan, mencari tiket, memberi tahu petugas.',
                'outputs' => 'validated',
            ],
            self::AI => [
                'label' => 'Tanya Jawab AI',
                'icon' => 'sparkles',
                'description' => 'Percakapan bebas yang dijawab dari isi situs. Menahan giliran sampai pengguna selesai bertanya.',
                'outputs' => 'validated',
            ],
            self::END => [
                'label' => 'Selesai',
                'icon' => 'circle-check',
                'description' => 'Menutup percakapan. Pesan berikutnya memulai dari awal.',
                'outputs' => 'none',
            ],
        ];
    }

    /** What an `input` node may ask for. */
    public static function inputKinds(): array
    {
        return [
            'text' => 'Teks bebas',
            'number' => 'Angka',
            'image' => 'Gambar',
            'location' => 'Titik lokasi',
            'document' => 'Berkas',
            'image_location' => 'Gambar dan titik lokasi (keduanya wajib)',
        ];
    }

    /**
     * What an `action` node may do. Fixed: an action is code, not data.
     *
     * These read as sentences because they are chosen from a dropdown, where
     * an administrator needs to know what each one will do.
     */
    public static function actions(): array
    {
        return [
            'create_complaint' => 'Buat pengaduan dari jawaban yang terkumpul',
            'lookup_complaint' => 'Cari pengaduan berdasarkan nomor tiket',
            'notify_officers' => 'Beri tahu petugas sesuai kategori',
            'answer_question' => 'Jawab pertanyaan dari sumber data',
            'share_contact' => 'Catat pertanyaan lalu beri nomor WhatsApp bidangnya',
            'list_my_complaints' => 'Tampilkan pengaduan milik pelapor ini',
        ];
    }

    /**
     * The same actions in two or three words.
     *
     * A node on the canvas is about 210px wide, so the sentence above would be
     * clipped to something unreadable. This is what a node is named.
     *
     * @return array<string, string>
     */
    public static function actionNames(): array
    {
        return [
            'create_complaint' => 'Simpan Pengaduan',
            'lookup_complaint' => 'Cari Pengaduan',
            'notify_officers' => 'Beri Tahu Petugas',
            'answer_question' => 'Jawab Pertanyaan',
            'share_contact' => 'Beri Nomor Bidang',
            'list_my_complaints' => 'Daftar Aduan Saya',
        ];
    }

    /**
     * Content the bot may read out. A fixed list because a free-text model name
     * here would be a ready-made way to read the users table over WhatsApp.
     */
    public static function dataSources(): array
    {
        return [
            'news' => 'Berita',
            'services' => 'Layanan',
            'documents' => 'Dokumen',
            'faqs' => 'FAQ',
            'pages' => 'Halaman',
            'complaint_categories' => 'Jenis Pengaduan',
        ];
    }

    /** What a node does when the answer does not validate. */
    public static function retryBehaviours(): array
    {
        return [
            'repeat' => 'Ulangi pertanyaan yang sama',
            'main_menu' => 'Kembali ke menu utama',
            'previous' => 'Kembali ke node sebelumnya',
            'end' => 'Akhiri percakapan',
        ];
    }

    /**
     * Every setting a node may carry, and how it is validated.
     *
     * Declared rather than freeform for a reason found the hard way: a form
     * request returns only the keys it has rules for, so an undeclared setting
     * is silently dropped on save — the editor would quietly eat the very
     * configuration it was opened to change. Declaring the vocabulary means a
     * setting either round-trips or is rejected, never disappears.
     *
     * @return array<string, array<int, string>>
     */
    public static function configRules(): array
    {
        return [
            // Wording
            'text' => ['nullable', 'string', 'max:4000'],
            'footer' => ['nullable', 'string', 'max:500'],
            'invalid_message' => ['nullable', 'string', 'max:500'],
            'success_message' => ['nullable', 'string', 'max:2000'],
            'failure_message' => ['nullable', 'string', 'max:500'],
            'skip_label' => ['nullable', 'string', 'max:200'],
            'back_label' => ['nullable', 'string', 'max:120'],

            // Behaviour
            'input' => ['nullable', 'string'],
            'action' => ['nullable', 'string'],
            'data_source' => ['nullable', 'string', 'max:64'],
            'not_found_message' => ['nullable', 'string', 'max:500'],
            'category_slug' => ['nullable', 'string', 'max:64'],
            // AI node
            'sources' => ['nullable', 'array', 'max:5'],
            'sources.*' => ['string', 'max:64'],
            'persona' => ['nullable', 'string', 'max:1000'],
            'max_turns' => ['nullable', 'integer', 'between:1,20'],
            'closing_message' => ['nullable', 'string', 'max:500'],
            'on_invalid' => ['nullable', 'string'],
            'max_retries' => ['nullable', 'integer', 'between:1,10'],
            'store_as' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'min_length' => ['nullable', 'integer', 'between:0,2000'],
            'pattern' => ['nullable', 'string', 'max:200'],
            'requirement_from' => ['nullable', 'string', 'in:category'],
            'options_from' => ['nullable', 'string', 'in:complaint_categories'],
            'include_back' => ['nullable', 'boolean'],
            // Node masukan: balasan persis ini keluar lewat sambungan "back".
            'back_on' => ['nullable', 'string', 'max:16'],
            'skippable_when_optional' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
            // Dipakai share_contact ketika bidangnya belum diisi nomor.
            'fallback_message' => ['nullable', 'string', 'max:2000'],
            'from' => ['nullable', 'string', 'max:64'],

            // Collections
            'options' => ['nullable', 'array', 'max:20'],
            'triggers' => ['nullable', 'array', 'max:20'],
            'triggers.*' => ['string', 'max:64'],
        ];
    }

    public static function isType(string $type): bool
    {
        return isset(self::types()[$type]);
    }
}
