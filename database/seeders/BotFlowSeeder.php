<?php

namespace Database\Seeders;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotEdge;
use App\Models\Bot\BotFlow;
use App\Models\Bot\BotNode;
use App\Support\BotNodes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The default conversation, drawn as data.
 *
 * This is also the worked example of the node config contract: everything the
 * visual editor writes, the engine reads and the Python runtime executes has
 * the shape shown here. Nothing in it is special-cased in code — the photo and
 * location requirements come from the category rows, not from the node knowing
 * that "jalan" needs a picture.
 */
class BotFlowSeeder extends Seeder
{
    public function run(): void
    {
        $flow = BotFlow::firstOrCreate(
            ['slug' => 'alur-utama'],
            [
                'name' => 'Alur Utama',
                'description' => 'Menu utama: pengaduan, cek aduan, dan informasi layanan.',
                'is_active' => true,
                'is_default' => true,
                'version' => 1,
                'published_at' => now(),
            ],
        );

        // Re-running replaces the graph rather than adding to it, so an example
        // flow can be reset without leaving orphaned nodes behind.
        DB::transaction(function () use ($flow) {
            $flow->nodes()->delete();
            $flow->edges()->delete();

            foreach ($this->nodes() as $node) {
                BotNode::create($node + ['bot_flow_id' => $flow->id]);
            }

            foreach ($this->edges() as $index => $edge) {
                BotEdge::create($edge + ['bot_flow_id' => $flow->id, 'sort_order' => ($index + 1) * 10]);
            }
        });

        $this->seedDataSources();
        $this->seedChannels($flow);

        // Tidak ada lagi kategori "Pertanyaan" tersendiri di sini.
        //
        // Dulu setiap pertanyaan tanpa jawaban FAQ jatuh ke satu keranjang
        // bernama "Pertanyaan", sehingga hanya petugas yang memegang keranjang
        // itu yang dikabari — pertanyaan tentang irigasi pun tidak pernah
        // sampai ke petugas irigasi. Sejak node `topik_pertanyaan` menanyakan
        // bidangnya lebih dulu, pertanyaan masuk ke jenis yang sama dengan
        // pengaduan, dan sampai ke orang yang sama.

        $this->command?->info(sprintf(
            'Alur "%s": %d node, %d sambungan.',
            $flow->name, $flow->nodes()->count(), $flow->edges()->count(),
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function nodes(): array
    {
        return [
            ['key' => 'start', 'type' => BotNodes::START, 'label' => 'Mulai', 'position_x' => 40, 'position_y' => 260,
                'config' => ['triggers' => ['halo', 'hai', 'menu', '/start']]],

            ['key' => 'menu_utama', 'type' => BotNodes::MENU, 'label' => 'Menu Utama', 'position_x' => 260, 'position_y' => 260,
                'config' => [
                    'text' => "Selamat datang di layanan pengaduan {site_name}.\n\nSilakan pilih dengan membalas angkanya:",
                    'options' => [
                        ['value' => '1', 'label' => 'Pengaduan'],
                        ['value' => '2', 'label' => 'Cek Aduan'],
                        ['value' => '3', 'label' => 'Informasi Layanan'],
                        ['value' => '4', 'label' => 'Tanya Jawab'],
                    ],
                    'footer' => 'Balas 0 kapan saja untuk kembali ke menu ini.',
                    'invalid_message' => 'Pilihan tidak dikenali. Balas dengan angka yang tersedia.',
                    'on_invalid' => 'repeat',
                    'max_retries' => 3,
                ]],

            /* ------------------------------------------------------ pengaduan */

            ['key' => 'menu_kategori', 'type' => BotNodes::MENU, 'label' => 'Kategori Pengaduan', 'position_x' => 520, 'position_y' => 100,
                'config' => [
                    'text' => "Jenis pengaduan apa yang ingin Anda sampaikan?",
                    // Options are drawn from the category table, so adding a
                    // category adds a menu entry without touching this flow.
                    'options_from' => 'complaint_categories',
                    'include_back' => true,
                    'back_label' => 'Kembali ke Menu',
                    'invalid_message' => 'Pilihan tidak dikenali. Balas dengan angka yang tersedia.',
                    'on_invalid' => 'repeat',
                ]],

            ['key' => 'isi_aduan', 'type' => BotNodes::INPUT, 'label' => 'Uraian Aduan', 'position_x' => 780, 'position_y' => 100,
                'config' => [
                    // Menyebut kembali jenis yang dipilih beserta syaratnya.
                    //
                    // Dua hal sekaligus: salah pencet angka ketahuan di sini,
                    // saat membatalkannya masih murah — bukan setelah laporan
                    // tersimpan dengan jenis yang keliru. Dan syaratnya
                    // disebutkan SEBELUM foto diminta, sebab orang yang masih
                    // berdiri di depan jalan rusak dapat memotretnya saat itu
                    // juga, sedangkan orang yang sudah pulang harus kembali.
                    //
                    // {category} berasal dari jawaban menu jenis pengaduan;
                    // {requirements} dari centang foto/lokasi pada jenis itu,
                    // jadi menambah jenis baru tidak perlu menyentuh teks ini.
                    'text' => "Pengaduan yang Anda pilih: *{category}*\n(persyaratan: {requirements})\n\nCeritakan keluhannya secara singkat.\n\nContoh: Jalan berlubang di depan Balai Desa Sukamaju, sudah dua minggu.",
                    'input' => 'text',
                    'store_as' => 'description',
                    'min_length' => 10,
                    'invalid_message' => 'Uraiannya terlalu singkat. Mohon jelaskan sedikit lebih rinci.',
                    'on_invalid' => 'repeat',
                ]],

            ['key' => 'bukti_aduan', 'type' => BotNodes::INPUT, 'label' => 'Foto dan Lokasi', 'position_x' => 1040, 'position_y' => 100,
                'config' => [
                    'text' => "Kirimkan foto lokasinya, lalu bagikan titik lokasi melalui menu Lampirkan → Lokasi.",
                    // Which of the two is actually compulsory is decided by the
                    // chosen category's own rules, not fixed here.
                    'input' => 'image_location',
                    'requirement_from' => 'category',
                    'store_as' => 'evidence',
                    'skippable_when_optional' => true,
                    'skip_label' => 'Balas LEWATI bila tidak ada foto.',
                    'invalid_message' => 'Untuk kategori ini foto dan titik lokasi keduanya wajib. Silakan kirim yang belum ada.',
                    'on_invalid' => 'repeat',
                    'max_retries' => 3,
                ]],

            ['key' => 'simpan_aduan', 'type' => BotNodes::ACTION, 'label' => 'Simpan Pengaduan', 'position_x' => 1300, 'position_y' => 100,
                'config' => [
                    'action' => 'create_complaint',
                    'notify' => true,
                    'success_message' => "Pengaduan Anda tercatat.\n\nNomor tiket: *{ticket}*\nKategori: {category}\nStatus: {status}\n\nSimpan nomor tiket untuk memeriksa perkembangannya melalui menu Cek Aduan.",
                    'failure_message' => 'Maaf, pengaduan belum dapat disimpan. Silakan coba beberapa saat lagi.',
                ]],

            /* ------------------------------------------------------ cek aduan */

            // Shown before anything is asked: most people checking a report
            // are checking their own, and they should not have to have kept
            // the number.
            ['key' => 'aduan_saya', 'type' => BotNodes::ACTION, 'label' => 'Daftar Aduan Saya', 'position_x' => 520, 'position_y' => 300,
                'config' => [
                    'action' => 'list_my_complaints',
                    'not_found_message' => "Belum ada pengaduan atas nama nomor ini.\n\nBila Anda punya nomor tiket dari kanal lain, silakan masukkan.",
                ]],

            ['key' => 'minta_tiket', 'type' => BotNodes::INPUT, 'label' => 'Nomor atau Tiket', 'position_x' => 780, 'position_y' => 300,
                'config' => [
                    'text' => 'Balas nomor urut dari daftar di atas, atau ketik nomor tiketnya. Contoh: ADU-K7M2PQR9',
                    'input' => 'text',
                    'store_as' => 'ticket',
                    // Digits address the list just shown; ADU- addresses a
                    // ticket directly.
                    'pattern' => '^([0-9]{1,2}|ADU-[A-Z0-9]{8})$',
                    'invalid_message' => 'Balas dengan nomor urut pada daftar, atau nomor tiket seperti ADU-K7M2PQR9.',
                    'on_invalid' => 'repeat',
                ]],

            ['key' => 'cari_aduan', 'type' => BotNodes::ACTION, 'label' => 'Cari Pengaduan', 'position_x' => 1040, 'position_y' => 300,
                'config' => [
                    'action' => 'lookup_complaint',
                    'from' => 'ticket',
                    'success_message' => "Tiket *{ticket}*\nKategori: {category}\nStatus: *{status}*\nDilaporkan: {created_at}\n\n{latest_update}",
                    // Says "not found" whether the ticket never existed or
                    // belongs to someone else. Distinguishing the two would let
                    // anyone test which tickets exist.
                    'failure_message' => 'Nomor tiket tidak ditemukan. Periksa kembali penulisannya.',
                ]],

            /* -------------------------------------------------------- informasi */

            ['key' => 'menu_informasi', 'type' => BotNodes::MENU, 'label' => 'Jenis Informasi', 'position_x' => 520, 'position_y' => 500,
                'config' => [
                    'text' => 'Informasi apa yang Anda butuhkan?',
                    'options' => [
                        ['value' => '1', 'label' => 'Berita Terbaru'],
                        ['value' => '2', 'label' => 'Layanan'],
                        ['value' => '3', 'label' => 'Dokumen'],
                        // Ditambahkan di nomor berikutnya yang kosong, bukan
                        // disisipkan: nomor ini yang dibalas warga, dan
                        // menggesernya mengubah arti balasan orang yang sedang
                        // berada di tengah percakapan.
                        ['value' => '4', 'label' => 'Jenis Pengaduan'],
                    ],
                    'include_back' => true,
                    'on_invalid' => 'repeat',
                ]],

            ['key' => 'daftar_berita', 'type' => BotNodes::DATA_SOURCE, 'label' => 'Daftar Berita', 'position_x' => 780, 'position_y' => 440,
                'config' => ['data_source' => 'berita', 'on_invalid' => 'repeat']],

            ['key' => 'daftar_layanan', 'type' => BotNodes::DATA_SOURCE, 'label' => 'Daftar Layanan', 'position_x' => 780, 'position_y' => 560,
                'config' => ['data_source' => 'layanan', 'on_invalid' => 'repeat']],

            ['key' => 'daftar_dokumen', 'type' => BotNodes::DATA_SOURCE, 'label' => 'Daftar Dokumen', 'position_x' => 780, 'position_y' => 680,
                'config' => ['data_source' => 'dokumen', 'on_invalid' => 'repeat']],

            // Menjawab "apa saja yang bisa diadukan, dan apa yang perlu
            // disiapkan" tanpa memaksa orang masuk ke alur pengaduan dulu.
            // Isinya dibaca dari tabel jenis pengaduan, jadi mencentang syarat
            // bukti di panel langsung mengubah apa yang dibacakan di sini.
            ['key' => 'daftar_jenis_pengaduan', 'type' => BotNodes::DATA_SOURCE, 'label' => 'Jenis Pengaduan', 'position_x' => 780, 'position_y' => 800,
                'config' => ['data_source' => 'jenis-pengaduan', 'on_invalid' => 'repeat']],

            /* ------------------------------------------------------ tanya jawab */

            // Holds its turn, so a follow-up is understood as a follow-up
            // rather than a fresh stranger.
            ['key' => 'tanya_ai', 'type' => BotNodes::AI, 'label' => 'Tanya Jawab', 'position_x' => 520, 'position_y' => 860,
                'config' => [
                    'text' => "Silakan tanyakan apa saja seputar layanan kami.\n\nContoh: Berapa lama proses izin mendirikan bangunan?",
                    'sources' => ['faq', 'layanan', 'berita', 'dokumen'],
                    'persona' => 'Anda petugas layanan informasi sebuah dinas pekerjaan umum dan penataan ruang. Ramah, ringkas, dan tidak pernah menjanjikan sesuatu yang tidak tertulis pada sumber.',
                    'max_turns' => 8,
                    'closing_message' => 'Baik, terima kasih sudah menghubungi kami. Balas *menu* kapan saja bila ada yang lain.',
                ]],

            // Reached when the model cannot answer from the site's own content,
            // or when assistance is switched off entirely.
            ['key' => 'minta_pertanyaan', 'type' => BotNodes::INPUT, 'label' => 'Tulis Pertanyaan', 'position_x' => 520, 'position_y' => 1080,
                'config' => [
                    'text' => "Silakan tulis pertanyaan Anda.\n\nContoh: Berapa lama proses izin mendirikan bangunan?",
                    'input' => 'text',
                    'store_as' => 'question',
                    'min_length' => 8,
                    'invalid_message' => 'Pertanyaannya terlalu singkat. Mohon tuliskan sedikit lebih lengkap.',
                    'on_invalid' => 'repeat',
                ]],

            ['key' => 'jawab_pertanyaan', 'type' => BotNodes::ACTION, 'label' => 'Cari di FAQ', 'position_x' => 780, 'position_y' => 1080,
                'config' => [
                    'action' => 'answer_question',
                    'data_source' => 'faq',
                    'from' => 'question',
                    'not_found_message' => "Pertanyaan Anda belum ada jawabannya di sini.\n\nAkan kami teruskan kepada petugas, dan jawabannya dikirimkan melalui percakapan ini.",
                ]],

            // Terjawab bukan berarti selesai.
            //
            // Satu pertanyaan hampir tidak pernah berdiri sendiri: yang
            // bertanya berapa lama izin terbit biasanya bertanya biayanya
            // sesudah itu. Mengembalikannya ke menu utama setelah satu jawaban
            // memaksanya menempuh 4 → tulis lagi untuk pertanyaan kedua, dan
            // itulah yang membuat sebuah percakapan terasa seperti formulir.
            ['key' => 'pertanyaan_lagi', 'type' => BotNodes::INPUT, 'label' => 'Ada Pertanyaan Lagi', 'position_x' => 1040, 'position_y' => 1200,
                'config' => [
                    'text' => "Ada pertanyaan lagi? Silakan tulis.\n\nBalas *0* untuk kembali ke menu utama.",
                    'input' => 'text',
                    'store_as' => 'question',
                    'min_length' => 8,
                    // Jalan keluarnya disebutkan pada teks di atas, jadi ia
                    // harus benar-benar ada: nol keluar lewat sambungan "back".
                    'back_on' => '0',
                    'invalid_message' => 'Pertanyaannya terlalu singkat. Mohon tuliskan sedikit lebih lengkap, atau balas *0* untuk kembali ke menu utama.',
                    'on_invalid' => 'repeat',
                ]],

            // Ditanyakan sebelum diteruskan, supaya pertanyaannya sampai ke
            // orang yang memang mengurusnya.
            //
            // Tanpa langkah ini setiap pertanyaan masuk ke kategori
            // "Pertanyaan", sehingga hanya petugas yang memegang kategori itu
            // yang dikabari — pertanyaan tentang irigasi pun tidak pernah
            // sampai ke petugas irigasi. Pilihannya diambil dari tabel
            // kategori, jadi kategori baru langsung muncul di sini.
            ['key' => 'topik_pertanyaan', 'type' => BotNodes::MENU, 'label' => 'Topik Pertanyaan', 'position_x' => 780, 'position_y' => 1080,
                'config' => [
                    'text' => "Agar sampai ke orang yang tepat, termasuk bidang apa pertanyaan ini?",
                    'options_from' => 'complaint_categories',
                    'include_back' => true,
                    'back_label' => 'Batal',
                    'invalid_message' => 'Pilihan tidak dikenali. Balas dengan angka yang tersedia.',
                    'on_invalid' => 'repeat',
                ]],

            // Not found is not a dead end: warga diberi nomor yang dapat
            // ditanyainya langsung, bukan tiket dan ajakan menunggu.
            //
            // Untuk pertanyaan — bukan laporan — menunggu adalah jawaban yang
            // buruk: yang dicari orang biasanya satu keterangan yang selesai
            // dalam semenit bila ditanyakan kepada orangnya.
            //
            // Nomornya diambil dari bidang yang dipilih di atas, jadi bidang
            // baru cukup diisi nomornya di panel tanpa menyentuh alur ini.
            ['key' => 'teruskan_pertanyaan', 'type' => BotNodes::ACTION, 'label' => 'Beri Nomor Bidang', 'position_x' => 1040, 'position_y' => 960,
                'config' => [
                    'action' => 'share_contact',
                    // Tanpa category_slug, ComplaintFiler memakai kategori yang
                    // dipilih pada node topik_pertanyaan di atas.
                    // Tidak menyebut "diteruskan ke petugas" di sini, dan itu
                    // disengaja: pada cabang ini grup memang tidak dikabari.
                    // Menjanjikan balasan yang tidak akan datang jauh lebih
                    // buruk daripada tidak menjanjikan apa-apa.
                    'success_message' => "Silakan hubungi nomor ini untuk melanjutkan:\n\n{contact}\n\nPertanyaan Anda juga sudah tercatat dengan nomor *{ticket}*.",
                    // Dipakai bila bidang itu belum diisi nomornya di panel.
                    // Isinya sama persis, kecuali tidak menyebut nomor yang
                    // memang tidak ada: kolom yang belum diisi tidak boleh
                    // berakhir sebagai warga yang tidak diberi apa-apa.
                    'fallback_message' => "Pertanyaan Anda sudah tercatat dengan nomor *{ticket}* dan diteruskan kepada petugas {category}.\n\nPetugas akan menjawabnya melalui percakapan ini.",
                    'failure_message' => 'Maaf, pertanyaan belum dapat dicatat. Silakan coba beberapa saat lagi.',
                ]],

            ['key' => 'selesai', 'type' => BotNodes::END, 'label' => 'Selesai', 'position_x' => 1560, 'position_y' => 300,
                'config' => ['text' => 'Terima kasih. Balas *menu* kapan saja untuk memulai lagi.']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function edges(): array
    {
        return [
            ['from_node' => 'start', 'to_node' => 'menu_utama', 'condition' => null],

            ['from_node' => 'menu_utama', 'to_node' => 'menu_kategori', 'condition' => '1', 'label' => 'Pengaduan'],
            ['from_node' => 'menu_utama', 'to_node' => 'aduan_saya', 'condition' => '2', 'label' => 'Cek Aduan'],
            ['from_node' => 'menu_utama', 'to_node' => 'menu_informasi', 'condition' => '3', 'label' => 'Informasi'],
            ['from_node' => 'menu_utama', 'to_node' => 'tanya_ai', 'condition' => '4', 'label' => 'Tanya Jawab'],

            // Every category leads to the same three steps; what differs is
            // whether evidence is compulsory, and that comes from the category.
            ['from_node' => 'menu_kategori', 'to_node' => 'isi_aduan', 'condition' => 'category', 'label' => 'Kategori dipilih'],
            ['from_node' => 'menu_kategori', 'to_node' => 'menu_utama', 'condition' => 'back', 'label' => 'Kembali'],

            ['from_node' => 'isi_aduan', 'to_node' => 'bukti_aduan', 'condition' => 'valid'],
            ['from_node' => 'bukti_aduan', 'to_node' => 'simpan_aduan', 'condition' => 'valid'],
            ['from_node' => 'bukti_aduan', 'to_node' => 'menu_utama', 'condition' => 'exhausted', 'label' => 'Gagal berulang'],

            ['from_node' => 'simpan_aduan', 'to_node' => 'selesai', 'condition' => 'valid'],
            ['from_node' => 'simpan_aduan', 'to_node' => 'menu_utama', 'condition' => 'invalid'],

            ['from_node' => 'aduan_saya', 'to_node' => 'minta_tiket', 'condition' => 'valid'],
            ['from_node' => 'aduan_saya', 'to_node' => 'minta_tiket', 'condition' => 'invalid'],
            ['from_node' => 'minta_tiket', 'to_node' => 'cari_aduan', 'condition' => 'valid'],
            ['from_node' => 'minta_tiket', 'to_node' => 'menu_utama', 'condition' => 'exhausted'],
            ['from_node' => 'cari_aduan', 'to_node' => 'selesai', 'condition' => 'valid'],
            ['from_node' => 'cari_aduan', 'to_node' => 'menu_utama', 'condition' => 'invalid'],

            ['from_node' => 'menu_informasi', 'to_node' => 'daftar_berita', 'condition' => '1'],
            ['from_node' => 'menu_informasi', 'to_node' => 'daftar_layanan', 'condition' => '2'],
            ['from_node' => 'menu_informasi', 'to_node' => 'daftar_dokumen', 'condition' => '3'],
            ['from_node' => 'menu_informasi', 'to_node' => 'daftar_jenis_pengaduan', 'condition' => '4'],
            ['from_node' => 'menu_informasi', 'to_node' => 'menu_utama', 'condition' => 'back'],

            ['from_node' => 'daftar_berita', 'to_node' => 'menu_utama', 'condition' => 'valid'],
            ['from_node' => 'daftar_layanan', 'to_node' => 'menu_utama', 'condition' => 'valid'],
            ['from_node' => 'daftar_dokumen', 'to_node' => 'menu_utama', 'condition' => 'valid'],
            ['from_node' => 'daftar_jenis_pengaduan', 'to_node' => 'menu_utama', 'condition' => 'valid'],

            // Finished asking, or the limit was reached — either way, done.
            ['from_node' => 'tanya_ai', 'to_node' => 'selesai', 'condition' => 'valid'],
            ['from_node' => 'tanya_ai', 'to_node' => 'selesai', 'condition' => 'exhausted'],
            // The model could not answer from the site. The question is
            // already remembered, so it goes straight to the keyword search
            // and then to a person — asking somebody to type the same sentence
            // again is exactly what makes a bot feel like a form.
            ['from_node' => 'tanya_ai', 'to_node' => 'jawab_pertanyaan', 'condition' => 'invalid'],
            // No assistance configured at all: ask for the question the plain
            // way, since nothing has been collected yet.
            ['from_node' => 'tanya_ai', 'to_node' => 'minta_pertanyaan', 'condition' => 'unavailable'],

            ['from_node' => 'minta_pertanyaan', 'to_node' => 'jawab_pertanyaan', 'condition' => 'valid'],
            ['from_node' => 'minta_pertanyaan', 'to_node' => 'menu_utama', 'condition' => 'exhausted'],
            // Terjawab → ditawari bertanya lagi; tidak terjawab → diserahkan
            // kepada orang. Tidak pernah buntu, dan tidak pernah memaksa
            // mengulang dari menu hanya untuk satu pertanyaan susulan.
            ['from_node' => 'jawab_pertanyaan', 'to_node' => 'pertanyaan_lagi', 'condition' => 'valid'],
            ['from_node' => 'jawab_pertanyaan', 'to_node' => 'topik_pertanyaan', 'condition' => 'invalid'],

            ['from_node' => 'pertanyaan_lagi', 'to_node' => 'jawab_pertanyaan', 'condition' => 'valid', 'label' => 'Pertanyaan berikutnya'],
            ['from_node' => 'pertanyaan_lagi', 'to_node' => 'menu_utama', 'condition' => 'back', 'label' => 'Balas 0'],
            ['from_node' => 'pertanyaan_lagi', 'to_node' => 'menu_utama', 'condition' => 'exhausted'],

            // Nol dari dalam percakapan AI juga kembali ke menu utama.
            ['from_node' => 'tanya_ai', 'to_node' => 'menu_utama', 'condition' => 'back', 'label' => 'Balas 0'],
            ['from_node' => 'topik_pertanyaan', 'to_node' => 'teruskan_pertanyaan', 'condition' => 'category', 'label' => 'Bidang dipilih'],
            ['from_node' => 'topik_pertanyaan', 'to_node' => 'menu_utama', 'condition' => 'back', 'label' => 'Batal'],
            ['from_node' => 'teruskan_pertanyaan', 'to_node' => 'selesai', 'condition' => 'valid'],
            ['from_node' => 'teruskan_pertanyaan', 'to_node' => 'menu_utama', 'condition' => 'invalid'],
        ];
    }

    private function seedDataSources(): void
    {
        $sources = [
            ['name' => 'Berita', 'slug' => 'berita', 'source' => 'news', 'limit' => 5,
                'list_template' => '{index}. {title} ({date})',
                'detail_template' => "*{title}*\n{date}\n\n{excerpt}\n\nSelengkapnya: {url}"],
            ['name' => 'Layanan', 'slug' => 'layanan', 'source' => 'services', 'limit' => 8,
                'list_template' => '{index}. {title}',
                'detail_template' => "*{title}*\n\n{excerpt}\n\nInformasi lengkap: {url}"],
            ['name' => 'Dokumen', 'slug' => 'dokumen', 'source' => 'documents', 'limit' => 8,
                'list_template' => '{index}. {title}',
                'detail_template' => "*{title}*\n\nUnduh: {url}"],
            // Tanpa {url}: jenis pengaduan tidak punya halaman publik sendiri,
            // dan penanda yang selalu kosong hanya menyisakan baris menggantung
            // di akhir pesan.
            ['name' => 'Jenis Pengaduan', 'slug' => 'jenis-pengaduan', 'source' => 'complaint_categories', 'limit' => 12,
                'list_template' => '{index}. {title}',
                'detail_template' => "*{title}*\n\n{excerpt}"],
            // Searched rather than listed: this is what answers a question.
            ['name' => 'Tanya Jawab', 'slug' => 'faq', 'source' => 'faqs', 'limit' => 10,
                'list_template' => '{index}. {title}',
                'detail_template' => "*{title}*\n\n{excerpt}"],
        ];

        foreach ($sources as $source) {
            BotDataSource::firstOrCreate(['slug' => $source['slug']], $source + ['is_active' => true]);
        }
    }

    private function seedChannels(BotFlow $flow): void
    {
        foreach (BotChannel::KEYS as $key => $name) {
            BotChannel::firstOrCreate(['key' => $key], [
                'name' => $name,
                // Off until someone has put the credentials in place and
                // checked the flow: a channel that answers the public the
                // moment it is seeded is a channel nobody reviewed.
                'is_active' => false,
                'bot_flow_id' => $flow->id,
                'settings' => ['greeting' => 'Halo! Ketik *menu* untuk memulai.'],
            ]);
        }
    }
}
