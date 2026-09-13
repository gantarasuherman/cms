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

    public function test_a_category_that_demands_no_evidence_accepts_a_skip(): void
    {
        $this->say('halo');
        $this->say('1');
        $this->say('3');  // Pengaduan Lainnya
        $this->say('Lampu jalan mati di gang tiga sejak pekan lalu.');

        $reply = $this->say('LEWATI');

        $complaint = Complaint::firstOrFail();
        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertCount(0, $complaint->evidence);
        $this->assertNull($complaint->latitude);
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
        $reply = $this->ask('Apakah ada bantuan perbaikan rumah tidak layak huni?');

        $complaint = Complaint::firstOrFail();

        // A bot that says "I don't know" and stops wastes whoever asked.
        $this->assertStringContainsString('teruskan kepada petugas', mb_strtolower($reply));
        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertSame('Apakah ada bantuan perbaikan rumah tidak layak huni?', $complaint->description);
        $this->assertSame('pertanyaan', $complaint->category?->slug);
        $this->assertSame('baru', $complaint->status);
    }

    public function test_an_unanswered_question_notifies_whoever_covers_questions(): void
    {
        $recipient = BotRecipient::create([
            'name' => 'Layanan Informasi', 'channel' => 'telegram',
            'destination' => '-100999', 'is_active' => true, 'can_command' => true,
        ]);
        $recipient->categories()->attach(ComplaintCategory::where('slug', 'pertanyaan')->value('id'));

        $this->ask('Kapan jadwal pelayanan hari Sabtu dibuka kembali?');

        $queued = DB::table('bot_outbox')->get();

        $this->assertCount(1, $queued);
        $this->assertSame('-100999', $queued->first()->destination);
        $this->assertStringContainsString('Pertanyaan', $queued->first()->body);
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
        $ticket = Complaint::firstOrFail()->ticket;

        $this->say('menu');
        $this->say('2');

        $this->assertStringContainsString('Pertanyaan', $this->say($ticket));
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
