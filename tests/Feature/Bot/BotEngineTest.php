<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotMessage;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Services\Bot\BotEngine;
use App\Services\Bot\Messages\IncomingMessage;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BotEngineTest extends TestCase
{
    use RefreshDatabase;

    private BotChannel $channel;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);

        $this->channel = BotChannel::where('key', 'whatsapp')->firstOrFail();
        $this->channel->update(['is_active' => true]);
    }

    /** One turn of the conversation. Returns everything the bot said. */
    private function say(string $text, array $overrides = []): string
    {
        $message = new IncomingMessage(
            externalId: 'wamid.'.(++$this->counter),
            from: '628120000001',
            type: $overrides['type'] ?? 'text',
            text: $text,
            mediaPath: $overrides['mediaPath'] ?? null,
            latitude: $overrides['latitude'] ?? null,
            longitude: $overrides['longitude'] ?? null,
            senderName: 'Warga Sukamaju',
        );

        $replies = app(BotEngine::class)->handle($this->channel, $message);

        return implode("\n", array_map(fn ($reply) => $reply->body, $replies));
    }

    private function conversation(): BotConversation
    {
        return BotConversation::latest('id')->firstOrFail();
    }

    /* ---------------------------------------------------------- menu utama */

    public function test_a_greeting_opens_the_main_menu(): void
    {
        $reply = $this->say('halo');

        $this->assertStringContainsString('1. Pengaduan', $reply);
        $this->assertStringContainsString('2. Cek Aduan', $reply);
        $this->assertStringContainsString('3. Informasi Layanan', $reply);
        $this->assertSame('menu_utama', $this->conversation()->current_node);
    }

    public function test_the_site_name_is_filled_into_the_greeting(): void
    {
        // Written as {site_name} by an administrator, not hard-coded.
        $this->assertStringContainsString('Dynamic CMS', $this->say('halo'));
    }

    public function test_an_option_can_be_chosen_by_its_words_as_well_as_its_number(): void
    {
        $this->say('halo');

        // People type "pengaduan" as readily as they type "1".
        $this->assertStringContainsString('Jenis pengaduan', $this->say('Pengaduan'));
    }

    public function test_an_unknown_choice_is_refused_without_moving_on(): void
    {
        $this->say('halo');
        $reply = $this->say('99');

        $this->assertStringContainsString('tidak dikenali', $reply);
        $this->assertSame('menu_utama', $this->conversation()->current_node);
    }

    /* ------------------------------------------------- pengaduan lengkap */

    public function test_a_complaint_can_be_filed_from_start_to_ticket(): void
    {
        $this->say('halo');
        $this->assertStringContainsString('Pengaduan Jalan', $this->say('1'));

        $this->assertStringContainsString('Ceritakan keluhannya', $this->say('1'));

        $reply = $this->say('Jalan berlubang di depan Balai Desa Sukamaju, sudah dua minggu.');
        $this->assertStringContainsString('foto', mb_strtolower($reply));

        // The photograph and the pin arrive as two separate messages, which is
        // how a person actually sends them.
        $this->say('', ['type' => 'image', 'mediaPath' => 'bot/foto-jalan.jpg']);
        $reply = $this->say('', ['type' => 'location', 'latitude' => -6.2088, 'longitude' => 106.8456]);

        $complaint = Complaint::firstOrFail();

        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertSame('Jalan berlubang di depan Balai Desa Sukamaju, sudah dua minggu.', $complaint->description);
        $this->assertSame('jalan', $complaint->category?->slug);
        $this->assertSame('baru', $complaint->status);
        $this->assertEqualsWithDelta(-6.2088, (float) $complaint->latitude, 0.0001);
        $this->assertCount(1, $complaint->evidence);
    }

    public function test_a_photo_sent_first_is_not_asked_for_twice(): void
    {
        $this->openEvidenceStep();

        $reply = $this->say('', ['type' => 'image', 'mediaPath' => 'bot/foto.jpg']);

        // Only the pin is still missing.
        $this->assertStringContainsString('titik lokasi', $reply);
        $this->assertStringNotContainsString('Belum ada: foto', $reply);
    }

    public function test_a_category_that_demands_no_evidence_is_never_asked_for_any(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('3');  // Pengaduan Lainnya

        // Laporan terbit langsung dari uraiannya. Keluhan yang tidak punya
        // wujud — pelayanan lambat, antrean tak jelas — tidak perlu melewati
        // pertanyaan foto yang satu-satunya jawaban masuk akalnya "lewati".
        $reply = $this->say('Pelayanan di loket lambat dan tidak ada antrean jelas.');

        $complaint = Complaint::firstOrFail();
        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertStringNotContainsString('LEWATI', $reply);
        $this->assertCount(0, $complaint->evidence);
        $this->assertNull($complaint->latitude);
    }

    public function test_ticking_one_requirement_brings_the_question_back(): void
    {
        ComplaintCategory::where('slug', 'lainnya')->update(['requires_photo' => true]);

        $this->say('halo');
        $this->say('1');
        $this->say('3');

        // Syaratnya baris data: mencentangnya di panel mengubah percakapan
        // tanpa ada yang menyentuh alurnya.
        $reply = $this->say('Lampu jalan mati di gang tiga sejak pekan lalu.');

        $this->assertStringContainsString('foto', mb_strtolower($reply));
        $this->assertSame(0, Complaint::count());
    }

    public function test_evidence_rules_come_from_the_category_row(): void
    {
        // Making "Lainnya" demand a photograph must change the conversation
        // without anybody touching the flow.
        ComplaintCategory::where('slug', 'lainnya')->update(['requires_photo' => true]);

        $this->say('halo');
        $this->say('1');
        $this->say('3');
        $this->say('Lampu jalan mati di gang tiga.');

        $this->assertStringContainsString('Belum ada: foto', $this->say('LEWATI'));
        $this->assertSame(0, Complaint::count());
    }

    /* ------------------------------------------- jenis yang dipilih warga */

    public function test_the_chosen_kind_is_named_back_before_anything_is_typed(): void
    {
        $this->say('halo');
        $this->say('1');

        $reply = $this->say('3');

        // Salah pencet angka ketahuan di sini, saat membatalkannya masih
        // murah — bukan setelah laporan tersimpan dengan jenis yang keliru.
        $this->assertStringContainsString('Pengaduan Lainnya', $reply);
    }

    public function test_the_requirements_are_stated_before_the_photo_is_asked_for(): void
    {
        ComplaintCategory::where('slug', 'lainnya')
            ->update(['requires_photo' => true, 'requires_location' => true]);

        $this->say('halo');
        $this->say('1');

        // Disebut SEBELUM uraian diminta: orang yang masih berdiri di depan
        // jalan rusak dapat memotretnya saat itu juga, sedangkan orang yang
        // sudah pulang harus kembali ke sana.
        $this->assertStringContainsString('foto dan titik lokasi', $this->say('3'));
    }

    public function test_the_requirements_follow_the_ticks_on_that_kind(): void
    {
        ComplaintCategory::where('slug', 'lainnya')
            ->update(['requires_photo' => true, 'requires_location' => false]);

        $this->say('halo');
        $this->say('1');
        $reply = $this->say('3');

        // Hanya yang benar-benar diminta. Menyebut titik lokasi pada jenis
        // yang tidak memerlukannya membuat warga menyiapkan sesuatu yang
        // tidak akan pernah ditagih.
        $this->assertStringContainsString('persyaratan: foto', $reply);
        $this->assertStringNotContainsString('titik lokasi', $reply);
    }

    public function test_a_kind_that_needs_nothing_says_so_plainly(): void
    {
        $this->say('halo');
        $this->say('1');

        // "Lainnya" bawaan tidak mewajibkan apa pun. Mengosongkan tanda kurung
        // akan terbaca seperti ada yang gagal dimuat.
        $this->assertStringContainsString('tidak ada lampiran wajib', $this->say('3'));
    }

    public function test_a_newly_added_kind_needs_no_change_to_the_flow(): void
    {
        ComplaintCategory::create([
            'name' => 'Drainase Perkotaan', 'slug' => 'drainase-perkotaan',
            'requires_photo' => true, 'requires_location' => true,
            'is_active' => true, 'sort_order' => 99,
        ]);

        $this->say('halo');
        $this->say('1');

        $last = (string) ComplaintCategory::active()->count();
        $reply = $this->say($last);

        // Jenis baru cukup ditambahkan di panel: namanya dan syaratnya ikut
        // muncul tanpa siapa pun menyentuh teks node di editor alur.
        $this->assertStringContainsString('Drainase Perkotaan', $reply);
        $this->assertStringContainsString('foto dan titik lokasi', $reply);
    }

    /* ------------------------------------------------- tanya jawab */

    /** Satu entri FAQ, supaya pertanyaannya benar-benar terjawab. */
    private function faq(): void
    {
        \App\Models\Faq::create([
            'question' => 'Berapa lama proses izin mendirikan bangunan?',
            'answer' => 'Proses izin mendirikan bangunan memakan waktu lima hari kerja.',
            'is_active' => true, 'sort_order' => 10,
        ]);
    }

    public function test_one_answer_does_not_end_the_conversation(): void
    {
        $this->faq();
        $this->say('halo');
        $this->say('4');

        $reply = $this->say('Berapa lama proses izin mendirikan bangunan?');

        // Satu pertanyaan hampir tidak pernah berdiri sendiri: yang bertanya
        // berapa lama izin terbit biasanya menanyakan biayanya sesudah itu.
        $this->assertStringContainsString('Ada pertanyaan lagi', $reply);
        $this->assertStringNotContainsString('Balas *menu* kapan saja untuk memulai lagi', $reply);
    }

    public function test_a_second_question_is_answered_without_starting_over(): void
    {
        $this->faq();
        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lama proses izin mendirikan bangunan?');

        $reply = $this->say('Berapa biaya rekomendasi teknis bangunan gedung?');

        // Tidak dilempar kembali ke menu utama di antara dua pertanyaan —
        // itulah yang membuat percakapan terasa seperti formulir.
        $this->assertStringNotContainsString('Silakan pilih dengan membalas angkanya', $reply);
    }

    public function test_zero_returns_to_the_main_menu(): void
    {
        $this->faq();
        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lama proses izin mendirikan bangunan?');

        $reply = $this->say('0');

        // Jalan keluarnya disebutkan di layar itu, jadi ia harus benar-benar
        // ada. Sebelumnya satu-satunya cara keluar adalah mengetik "menu",
        // yang tidak pernah diberitahukan di sana.
        $this->assertStringContainsString('Silakan pilih dengan membalas angkanya', $reply);
    }

    public function test_zero_is_not_mistaken_for_a_question_too_short(): void
    {
        $this->faq();
        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lama proses izin mendirikan bangunan?');

        // Diperiksa sebelum panjangnya dinilai; kalau tidak, "0" ditolak
        // sebagai pertanyaan yang terlalu singkat dan orangnya terjebak.
        $this->assertStringNotContainsString('terlalu singkat', $this->say('0'));
    }

    /* ------------------------------------- pertanyaan yang tak terjawab */

    public function test_an_unanswered_question_hands_over_the_number_for_that_field(): void
    {
        ComplaintCategory::where('slug', 'jalan')->update([
            'contact_name' => 'Bidang Bina Marga',
            'contact_phone' => '628123456789',
        ]);

        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lebar minimal bahu jalan kabupaten?');

        $reply = $this->say('1');

        // Yang dicari orang yang bertanya biasanya satu keterangan yang selesai
        // dalam semenit bila ditanyakan kepada orangnya. Sebuah tiket membuatnya
        // menunggu sehari untuk itu.
        $this->assertStringContainsString('Silakan hubungi nomor ini', $reply);
        $this->assertStringContainsString('Bidang Bina Marga', $reply);
        $this->assertStringContainsString('628123456789', $reply);
        $this->assertStringContainsString('https://wa.me/628123456789', $reply);
        // Tidak menjanjikan balasan petugas: pada cabang ini grup tidak
        // dikabari, dan janji yang tidak akan ditepati lebih buruk daripada
        // tidak berjanji apa-apa.
        $this->assertStringNotContainsString('diteruskan kepada petugas', $reply);
    }

    public function test_the_number_follows_the_field_that_was_chosen(): void
    {
        ComplaintCategory::where('slug', 'jalan')->update(['contact_phone' => '628111111111']);
        ComplaintCategory::where('slug', 'irigasi')->update(['contact_phone' => '628222222222']);

        $this->say('halo');
        $this->say('4');
        $this->say('Kapan saluran irigasi dinormalisasi?');

        $reply = $this->say('2');

        // Memilih bidang kedua harus memberi nomor bidang kedua. Kekeliruan di
        // sini mengirim warga menelepon orang yang tidak mengurusnya.
        $this->assertStringContainsString('628222222222', $reply);
        $this->assertStringNotContainsString('628111111111', $reply);
    }

    public function test_the_question_is_still_recorded_so_it_can_become_an_faq(): void
    {
        ComplaintCategory::where('slug', 'jalan')->update(['contact_phone' => '628123456789']);

        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lebar minimal bahu jalan kabupaten?');
        $this->say('1');

        // Pertanyaan yang sama ditanyakan lima puluh orang adalah FAQ yang
        // belum ditulis. Berhenti mencatatnya memadamkan satu-satunya layar
        // tempat hal itu terlihat.
        $complaint = Complaint::firstOrFail();
        $this->assertSame('jalan', $complaint->category->slug);
        $this->assertStringContainsString('bahu jalan', $complaint->description);
    }

    public function test_the_group_is_left_alone_once_the_citizen_has_the_number(): void
    {
        ComplaintCategory::where('slug', 'jalan')->update(['contact_phone' => '628123456789']);

        BotRecipient::create([
            'name' => 'Petugas Jalan', 'channel' => 'whatsapp',
            'destination' => '628130000001', 'is_active' => true,
        ]);

        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lebar minimal bahu jalan kabupaten?');
        $this->say('1');

        // Warga sudah dipertemukan dengan orang yang mengurusnya, jadi pesan ke
        // grup tidak menambah apa pun — dan grup petugas yang berisik adalah
        // grup yang berhenti dibaca, sehingga laporan sungguhan ikut terlewat.
        $this->assertSame(0, DB::table('bot_outbox')->count());
        // Tetap tercatat: itulah yang menyalakan "Pertanyaan terbanyak".
        $this->assertSame(1, Complaint::count());
    }

    public function test_a_field_without_a_number_still_records_and_forwards(): void
    {
        BotRecipient::create([
            'name' => 'Petugas Jalan', 'channel' => 'whatsapp',
            'destination' => '628130000001', 'is_active' => true,
        ]);

        $this->say('halo');
        $this->say('4');
        $this->say('Berapa lebar minimal bahu jalan kabupaten?');

        $reply = $this->say('1');

        // Kolom yang belum diisi tidak boleh berakhir sebagai warga yang tidak
        // diberi apa-apa. Yang hilang hanya nomornya, bukan seluruh jawabannya.
        $this->assertStringContainsString('diteruskan kepada petugas', $reply);
        $this->assertStringNotContainsString('wa.me', $reply);
        $this->assertSame(1, DB::table('bot_outbox')->count());
    }

    public function test_a_description_that_is_too_short_is_refused(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('1');

        $this->assertStringContainsString('terlalu singkat', $this->say('rusak'));
        $this->assertSame('isi_aduan', $this->conversation()->current_node);
    }

    /* --------------------------------------------------------- cek aduan */

    public function test_a_ticket_can_be_looked_up(): void
    {
        $complaint = Complaint::create([
            'ticket' => Complaint::newTicket(),
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Jalan berlubang.',
            'status' => 'diproses',
        ]);

        $this->say('halo');
        $this->say('2');

        $reply = $this->say($complaint->ticket);

        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertStringContainsString('Sedang Diproses', $reply);
    }

    public function test_an_unknown_ticket_does_not_reveal_whether_it_exists(): void
    {
        $this->say('halo');
        $this->say('2');

        $reply = $this->say('ADU-ZZZZZZZZ');

        $this->assertStringContainsString('tidak ditemukan', $reply);
        // Same answer whether the ticket never existed or belongs to somebody
        // else, or trying tickets would map out which ones are real.
        $this->assertStringNotContainsString('milik', $reply);
    }

    public function test_a_malformed_ticket_is_caught_before_any_lookup(): void
    {
        $this->say('halo');
        $this->say('2');

        $this->assertStringContainsString('nomor tiket seperti ADU-', $this->say('12345'));
    }

    public function test_checking_a_report_lists_the_callers_own_first(): void
    {
        $mine = $this->fileRoadComplaint();

        $this->say('menu');
        $reply = $this->say('2');

        // Most people checking a report are checking their own, and should not
        // have to have kept the number.
        $this->assertStringContainsString('Pengaduan Anda:', $reply);
        $this->assertStringContainsString($mine->ticket, $reply);
        $this->assertStringContainsString('Balas nomornya', $reply);
    }

    public function test_a_row_number_opens_that_report(): void
    {
        $mine = $this->fileRoadComplaint();

        $this->say('menu');
        $this->say('2');
        $reply = $this->say('1');

        $this->assertStringContainsString($mine->ticket, $reply);
        $this->assertStringContainsString('Baru', $reply);
    }

    public function test_a_row_number_cannot_reach_somebody_elses_report(): void
    {
        // Filed by a different person entirely.
        $other = Complaint::create([
            'ticket' => Complaint::newTicket(),
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'channel' => 'whatsapp', 'description' => 'Aduan orang lain.', 'status' => 'baru',
        ]);

        $this->say('halo');
        $this->say('2');
        $reply = $this->say('1');

        // Row numbers resolve only against the caller's own list, so there is
        // nothing for "1" to point at.
        $this->assertStringNotContainsString($other->ticket, $reply);
        $this->assertStringContainsString('tidak ditemukan', $reply);
    }

    public function test_somebody_with_no_reports_is_told_so_and_can_still_type_a_ticket(): void
    {
        $existing = Complaint::create([
            'ticket' => Complaint::newTicket(),
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'channel' => 'whatsapp', 'description' => 'Aduan lama.', 'status' => 'selesai',
        ]);

        $this->say('halo');
        $this->assertStringContainsString('Belum ada pengaduan atas nama', $this->say('2'));

        // A ticket from another channel still works.
        $this->assertStringContainsString('Selesai', $this->say($existing->ticket));
    }

    /* ------------------------------------------- balasan di luar dugaan */

    public function test_the_wrong_kind_of_reply_is_named_along_with_what_was_wanted(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('1');

        // A voice note where a description was asked for.
        $reply = $this->say('', ['type' => 'audio']);

        $this->assertStringContainsString('Anda mengirim pesan suara', $reply);
        $this->assertStringContainsString('minimal 10 karakter', $reply);
    }

    public function test_a_sticker_where_evidence_was_asked_for_is_explained(): void
    {
        $this->openEvidenceStep();

        $reply = $this->say('', ['type' => 'sticker']);

        $this->assertStringContainsString('Anda mengirim stiker', $reply);
        $this->assertStringContainsString('Belum ada: foto dan titik lokasi', $reply);
    }

    public function test_a_refusal_says_what_a_valid_answer_looks_like(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('1');

        // Right kind of thing, wrong content: no need to say "you sent text".
        $reply = $this->say('rusak');

        $this->assertStringNotContainsString('Anda mengirim teks', $reply);
        $this->assertStringContainsString('terlalu singkat', $reply);
    }

    public function test_the_question_is_asked_again_once_someone_is_clearly_lost(): void
    {
        $this->say('halo');

        $first = $this->say('99');
        $second = $this->say('99');

        // The first refusal is just a refusal; by the second the original
        // question has scrolled away, so it comes back with it.
        $this->assertStringNotContainsString('1. Pengaduan', $first);
        $this->assertStringContainsString('1. Pengaduan', $second);
    }

    public function test_repeated_failure_follows_the_nodes_own_retry_rule(): void
    {
        $this->openEvidenceStep();

        // `bukti_aduan` is configured to give up after three tries and route
        // back to the main menu.
        $this->say('', ['type' => 'sticker']);
        $this->say('', ['type' => 'sticker']);
        $reply = $this->say('', ['type' => 'sticker']);

        $this->assertStringContainsString('1. Pengaduan', $reply);
        $this->assertSame('menu_utama', $this->conversation()->current_node);
        $this->assertSame(0, Complaint::count());
    }

    public function test_an_empty_message_does_not_count_as_an_answer(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('1');

        $reply = $this->say('   ');

        $this->assertStringContainsString('Yang ditunggu', $reply);
        $this->assertSame('isi_aduan', $this->conversation()->current_node);
    }

    /* ------------------------------------------------------ sumber data */

    public function test_content_is_listed_then_opened_by_its_number(): void
    {
        $this->seed(\Database\Seeders\DemoContentSeeder::class);

        $this->say('halo');
        $this->assertStringContainsString('Berita Terbaru', $this->say('3'));

        $list = $this->say('1');
        $this->assertMatchesRegularExpression('/^1\. /m', $list);
        $this->assertStringContainsString('Balas nomornya', $list);

        // The reply to "2" must be the second item that was actually offered.
        $offered = $this->conversation()->answer('_list.daftar_berita');
        $detail = $this->say('2');

        $this->assertStringContainsString($offered[1]['title'], $detail);
        $this->assertStringContainsString('/berita/', $detail);
    }

    public function test_a_number_outside_the_list_is_refused(): void
    {
        $this->seed(\Database\Seeders\DemoContentSeeder::class);

        $this->say('halo');
        $this->say('3');
        $this->say('1');

        // The refusal now names what a valid reply would be, because the
        // question has usually scrolled away on a phone.
        $this->assertStringContainsString('Yang ditunggu: nomor salah satu item', $this->say('99'));
    }

    public function test_an_empty_source_says_so_rather_than_showing_a_blank_list(): void
    {
        $this->say('halo');
        $this->say('3');

        // Nothing is published in this test, so there is nothing to list.
        $this->assertStringContainsString('Belum ada', $this->say('1'));
    }

    /* ------------------------------------------------------- tanya jawab */

    private function ask(string $question): string
    {
        $this->say('halo');
        $this->say('4');

        return $this->say($question);
    }

    /**
     * Menjawab menu "termasuk bidang apa" dengan kategori yang diminta.
     *
     * Nomornya dihitung dari urutan kategori, bukan ditulis tetap: menambah
     * satu kategori menggeser seluruh nomor sesudahnya, dan tes yang menghafal
     * angka akan gagal karena alasan yang tidak ada hubungannya dengan apa
     * yang sedang diuji.
     */
    private function chooseTopic(string $slug): string
    {
        $categories = ComplaintCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->values();

        $position = $categories->search($slug);

        $this->assertNotFalse($position, "Kategori {$slug} tidak ada pada menu.");

        return $this->say((string) ($position + 1));
    }

    public function test_a_question_answered_by_the_faq_gets_that_answer(): void
    {
        \App\Models\Faq::create([
            'question' => 'Berapa lama proses izin mendirikan bangunan?',
            'answer' => 'Proses izin mendirikan bangunan memakan waktu lima hari kerja.',
            'is_active' => true, 'sort_order' => 10,
        ]);

        $reply = $this->ask('Berapa lama proses izin mendirikan bangunan di sini?');

        $this->assertStringContainsString('lima hari kerja', $reply);
        // Answered, so nothing needed to reach a person.
        $this->assertSame(0, Complaint::count());
    }

    public function test_a_question_with_no_answer_reaches_a_person(): void
    {
        $asked = $this->ask('Apakah ada bantuan perbaikan rumah tidak layak huni?');
        $this->assertStringContainsString('teruskan kepada petugas', mb_strtolower($asked));

        // Bidangnya ditanyakan dulu, supaya pertanyaan sampai ke petugas yang
        // mengurusnya — bukan hanya ke pemegang kategori "Pertanyaan".
        $reply = $this->chooseTopic('lainnya');

        $complaint = Complaint::firstOrFail();

        // A bot that says "I don't know" and stops wastes whoever asked.
        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertSame('Apakah ada bantuan perbaikan rumah tidak layak huni?', $complaint->description);
        $this->assertSame('lainnya', $complaint->category?->slug);
        $this->assertSame('baru', $complaint->status);
    }

    public function test_an_unanswered_question_notifies_whoever_covers_questions(): void
    {
        $recipient = BotRecipient::create([
            'name' => 'Layanan Informasi', 'channel' => 'telegram',
            'destination' => '-100999', 'is_active' => true, 'can_command' => true,
        ]);
        $recipient->categories()->attach(ComplaintCategory::where('slug', 'lainnya')->value('id'));

        $this->ask('Kapan jadwal pelayanan hari Sabtu dibuka kembali?');
        $this->chooseTopic('lainnya');

        $queued = DB::table('bot_outbox')->get();

        $this->assertCount(1, $queued);
        $this->assertSame('-100999', $queued->first()->destination);

        // Pertanyaan tidak lagi punya keranjang sendiri: ia masuk ke jenis
        // yang sama dengan pengaduan, dan sampai ke petugas yang sama.
        $this->assertStringContainsString('Pengaduan Lainnya', $queued->first()->body);
    }

    public function test_the_search_ignores_words_too_short_to_mean_anything(): void
    {
        \App\Models\Faq::create([
            'question' => 'Bagaimana cara mengurus sertifikat laik fungsi?',
            'answer' => 'Ajukan melalui loket pelayanan dengan membawa dokumen teknis.',
            'is_active' => true, 'sort_order' => 10,
        ]);

        // "di" and "ke" match everything and would rank nothing.
        $this->assertSame(0, Complaint::count());
        $reply = $this->ask('di ke');

        $this->assertStringContainsString('terlalu singkat', $reply);
    }

    public function test_a_question_tracked_by_its_ticket_like_any_other(): void
    {
        $this->ask('Apakah tersedia layanan konsultasi teknis bangunan?');
        $this->chooseTopic('lainnya');
        $ticket = Complaint::firstOrFail()->ticket;

        $this->say('menu');
        $this->say('2');

        $this->assertStringContainsString('Pengaduan Lainnya', $this->say($ticket));
    }

    /* -------------------------------------------------------- notifikasi */

    public function test_only_the_officers_covering_the_category_are_told(): void
    {
        $jalan = ComplaintCategory::where('slug', 'jalan')->firstOrFail();
        $irigasi = ComplaintCategory::where('slug', 'irigasi')->firstOrFail();

        $roads = BotRecipient::create(['name' => 'Admin Jalan', 'channel' => 'whatsapp', 'destination' => '628120000777', 'is_active' => true]);
        $roads->categories()->attach($jalan);

        $water = BotRecipient::create(['name' => 'Grup Irigasi', 'channel' => 'telegram', 'destination' => '-100123', 'is_active' => true]);
        $water->categories()->attach($irigasi);

        $this->fileRoadComplaint();

        $queued = DB::table('bot_outbox')->get();

        $this->assertCount(1, $queued);
        $this->assertSame('628120000777', $queued->first()->destination);
        $this->assertStringContainsString(Complaint::firstOrFail()->ticket, $queued->first()->body);
        $this->assertStringContainsString('google.com/maps', $queued->first()->body);
    }

    public function test_nobody_covering_a_category_means_nobody_is_messaged(): void
    {
        $this->fileRoadComplaint();

        // Falling back to "tell everyone" would quietly undo the assignment an
        // administrator set up.
        $this->assertSame(0, DB::table('bot_outbox')->count());
        $this->assertSame(1, Complaint::count());
    }

    /* ------------------------------------------------------------ sesi */

    public function test_menu_escapes_a_half_finished_form(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('1');

        $reply = $this->say('menu');

        $this->assertStringContainsString('1. Pengaduan', $reply);
        $this->assertSame('menu_utama', $this->conversation()->current_node);
        $this->assertSame(0, Complaint::count());
    }

    public function test_an_expired_session_starts_again_rather_than_resuming(): void
    {
        $this->say('halo');
        $this->say('1');

        BotConversation::query()->update(['expires_at' => now()->subMinute()]);

        $this->assertStringContainsString('1. Pengaduan', $this->say('1'));
    }

    public function test_a_conversation_is_pinned_to_the_flow_version_it_began_on(): void
    {
        $this->say('halo');

        $this->assertSame(1, $this->conversation()->flow_version);
    }

    public function test_a_blocked_contact_gets_no_reply_at_all(): void
    {
        $this->say('halo');
        $this->conversation()->contact->update(['is_blocked' => true]);

        $this->assertSame('', $this->say('1'));
    }

    /* ------------------------------------------------------------ catatan */

    public function test_every_turn_is_written_to_the_transcript(): void
    {
        $this->say('halo');
        $this->say('1');

        $messages = BotMessage::orderBy('id')->get();

        $this->assertSame('in', $messages->first()->direction);
        $this->assertSame('halo', $messages->first()->body);
        $this->assertTrue($messages->where('direction', 'out')->isNotEmpty());
        // The node that produced each reply, so a transcript can be read back
        // against the flow that produced it.
        $this->assertSame('menu_utama', $messages->where('direction', 'out')->first()->node_key);
    }

    public function test_a_location_message_keeps_its_coordinates_on_the_transcript(): void
    {
        $this->openEvidenceStep();
        $this->say('', ['type' => 'location', 'latitude' => -6.9, 'longitude' => 107.6]);

        $logged = BotMessage::where('type', 'location')->firstOrFail();

        $this->assertEqualsWithDelta(-6.9, (float) $logged->latitude, 0.0001);
        $this->assertTrue($logged->hasLocation());
    }

    /* ------------------------------------------------------------ helpers */

    private function openEvidenceStep(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('1');
        $this->say('Jalan berlubang di depan Balai Desa Sukamaju.');
    }

    private function fileRoadComplaint(): Complaint
    {
        $this->openEvidenceStep();
        $this->say('', ['type' => 'image', 'mediaPath' => 'bot/foto.jpg']);
        $this->say('', ['type' => 'location', 'latitude' => -6.2, 'longitude' => 106.8]);

        return Complaint::latest('id')->firstOrFail();
    }
}
