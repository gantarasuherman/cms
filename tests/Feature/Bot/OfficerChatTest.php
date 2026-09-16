<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Nomor petugas adalah meja kerja, bukan pelapor.
 *
 * Yang diuji di sini adalah pemisahannya: nomor terdaftar tidak pernah melihat
 * menu warga, dan perintahnya dibatasi kategori yang ditugaskan.
 */
class OfficerChatTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'rahasia-internal';

    private const OFFICER = '628120000777';

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);

        config(['bot.enabled' => true, 'bot.internal_token' => self::TOKEN]);

        BotChannel::where('key', 'whatsapp')->update(['is_active' => true]);
    }

    private function officer(bool $canCommand = true, string $slug = 'irigasi'): BotRecipient
    {
        $recipient = BotRecipient::create([
            'name' => 'Petugas Irigasi', 'channel' => 'whatsapp',
            'destination' => self::OFFICER, 'is_active' => true, 'can_command' => $canCommand,
        ]);

        $recipient->categories()->attach(ComplaintCategory::where('slug', $slug)->value('id'));

        return $recipient;
    }

    private function say(string $text, string $from = self::OFFICER): TestResponse
    {
        return $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.inbound'), [
                'channel' => 'whatsapp',
                'external_id' => 'wamid.'.(++$this->counter),
                'from' => $from,
                'type' => 'text',
                'text' => $text,
            ]);
    }

    private function body(TestResponse $response): string
    {
        return implode("\n", array_column($response->json('messages') ?? [], 'body'));
    }

    private function complaint(string $slug = 'irigasi'): Complaint
    {
        return Complaint::create([
            'ticket' => 'ADU-TEST1234',
            'complaint_category_id' => ComplaintCategory::where('slug', $slug)->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Saluran irigasi tersumbat di Sudimampir.',
            'status' => 'baru',
        ]);
    }

    /* ------------------------------------------------------- pemisahannya */

    public function test_an_officer_never_gets_the_public_menu(): void
    {
        $this->officer();

        $reply = $this->body($this->say('halo'));

        // Menu warga dimulai dengan sapaan dan daftar bernomor.
        $this->assertStringNotContainsString('1. Sampaikan Pengaduan', $reply);
        // Dan tidak dibalas apa pun: lihat test di bawah.
        $this->assertSame('', $reply);
    }

    public function test_ordinary_talk_in_the_officer_group_is_left_alone(): void
    {
        $this->officer();

        // Nomor petugas sering berupa grup, tempat orang berbicara satu sama
        // lain sepanjang hari. Membalas setiap pesan biasa dengan daftar
        // perintah membuat bot menyela tiap kali ada yang mengetik — daftar
        // yang sama, berulang-ulang, sampai kabar pengaduan yang sebenarnya
        // tenggelam di antaranya.
        foreach (['halo', 'sudah saya cek pak', 'besok kita ke lokasi ya', '👍'] as $chatter) {
            $this->assertSame('', $this->body($this->say($chatter)),
                "Pesan biasa \"{$chatter}\" seharusnya tidak dibalas apa pun.");
        }
    }

    public function test_a_mistyped_command_still_gets_an_answer(): void
    {
        $this->officer();

        // Salah ketik yang dibalas diam membuat orang mengira botnya mati.
        $this->assertStringContainsString(
            'terdaftar sebagai petugas',
            $this->body($this->say('/selesa ADU-SALAHKETIK')),
        );
    }

    public function test_a_member_of_the_public_still_gets_the_menu(): void
    {
        $this->officer();

        $reply = $this->body($this->say('halo', '628129999999'));

        $this->assertStringContainsString('Selamat datang', $reply);
    }

    public function test_an_unknown_command_answers_with_the_command_list(): void
    {
        $this->officer();

        $reply = $this->body($this->say('/statuss ADU-TEST1234'));

        $this->assertStringContainsString('/selesai', $reply);
    }

    /* --------------------------------------------------------------- info */

    public function test_info_shows_the_state_of_a_complaint(): void
    {
        $this->officer();
        $this->complaint();

        $reply = $this->body($this->say('/info ADU-TEST1234'));

        $this->assertStringContainsString('ADU-TEST1234', $reply);
        $this->assertStringContainsString('Irigasi', $reply);
        $this->assertStringContainsString('Sudimampir', $reply);
    }

    public function test_info_without_a_ticket_explains_the_commands(): void
    {
        $this->officer();

        $this->assertStringContainsString('/info ADU-', $this->body($this->say('/info')));
    }

    public function test_info_refuses_a_complaint_outside_the_assigned_categories(): void
    {
        $this->officer(slug: 'irigasi');
        $this->complaint(slug: 'jalan');

        $reply = $this->body($this->say('/info ADU-TEST1234'));

        // Nomor tiket mudah ditebak; uraiannya memuat data pelapor.
        $this->assertStringContainsString('bukan kategori yang ditugaskan', $reply);
        $this->assertStringNotContainsString('Sudimampir', $reply);
    }

    /* ------------------------------------------------------------ perintah */

    public function test_an_officer_may_move_a_complaint_they_cover(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        $this->body($this->say('/proses ADU-TEST1234 sedang dikerjakan tim'));

        $this->assertSame('diproses', $complaint->fresh()->status);
    }

    public function test_a_recipient_without_the_tick_may_not_change_anything(): void
    {
        $this->officer(canCommand: false);
        $complaint = $this->complaint();

        $reply = $this->body($this->say('/selesai ADU-TEST1234'));

        $this->assertStringContainsString('tidak berwenang', $reply);
        $this->assertSame('baru', $complaint->fresh()->status);
    }

    public function test_the_help_says_which_categories_this_number_covers(): void
    {
        $this->officer();

        $this->assertStringContainsString('Irigasi', $this->body($this->say('/bantuan')));
    }

    public function test_every_spelling_of_help_brings_the_list_up(): void
    {
        $this->officer();

        // Orang mengetik apa yang biasa mereka pakai di bot lain; menolak
        // salah satunya hanya menyisakan orang yang mengira botnya rusak.
        foreach (['/help', '/bantuan', '/start'] as $spelling) {
            $this->assertStringContainsString(
                'Perintah yang tersedia', $this->body($this->say($spelling)),
                "Ejaan {$spelling} seharusnya menampilkan daftar perintah.",
            );
        }
    }

    public function test_the_list_says_how_to_bring_itself_back(): void
    {
        $this->officer();

        // Daftar yang tergulung ke atas tidak meninggalkan petunjuk apa pun
        // bila ia tidak menyebut namanya sendiri.
        $this->assertStringContainsString('/help', $this->body($this->say('/help')));
    }

    /* -------------------------------------------------------------- jawab */

    /** Pengaduan yang punya kontak, sehingga ada tujuan untuk dibalas. */
    private function complaintFromChat(): Complaint
    {
        $channel = BotChannel::where('key', 'whatsapp')->firstOrFail();

        $contact = \App\Models\Bot\BotContact::create([
            'bot_channel_id' => $channel->getKey(),
            'external_id' => '628121111222',
            'name' => 'Warga Sukamaju',
        ]);

        return Complaint::create([
            'ticket' => 'ADU-TANYA001',
            'complaint_category_id' => ComplaintCategory::where('slug', 'irigasi')->value('id'),
            'bot_contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'description' => 'Kapan saluran di blok C diperbaiki?',
            'status' => 'baru',
        ]);
    }

    public function test_an_officer_answers_the_reporter_from_chat(): void
    {
        $this->officer();
        $complaint = $this->complaintFromChat();

        $reply = $this->body($this->say('/jawab ADU-TANYA001 Perbaikan dijadwalkan pekan depan.'));

        $this->assertStringContainsString('Jawaban dikirim', $reply);

        // Berangkat lewat outbox, seperti kabar pengaduan — bukan dikirim
        // langsung dari permintaan yang menulisnya.
        $queued = DB::table('bot_outbox')->where('destination', '628121111222')->first();

        $this->assertNotNull($queued);
        $this->assertStringContainsString('Perbaikan dijadwalkan pekan depan.', $queued->body);
        $this->assertStringContainsString('ADU-TANYA001', $queued->body);
    }

    public function test_answering_does_not_close_the_complaint(): void
    {
        $this->officer();
        $complaint = $this->complaintFromChat();

        $reply = $this->body($this->say('/jawab ADU-TANYA001 Sedang kami tinjau.'));

        // Menjawab sebagian tidak sama dengan menuntaskan; petugas tidak boleh
        // terpaksa menutup berkas karena sudah terlanjur mengetik.
        $this->assertSame('baru', $complaint->fresh()->status);
        $this->assertStringContainsString('/selesai', $reply);
    }

    public function test_the_answer_is_written_onto_the_history(): void
    {
        $this->officer();
        $complaint = $this->complaintFromChat();

        $this->say('/jawab ADU-TANYA001 Sudah kami teruskan ke lapangan.');

        $update = $complaint->fresh()->updates()->latest('id')->firstOrFail();

        // Tercatat atas nama petugasnya, bukan "admin" — grup petugas tidak
        // punya baris users, dan riwayat yang menyebut orang keliru lebih buruk
        // daripada riwayat yang kosong.
        $this->assertStringContainsString('Sudah kami teruskan', (string) $update->note);
        $this->assertStringContainsString('Petugas Irigasi', (string) $update->source_actor);
    }

    public function test_an_answer_without_words_explains_itself(): void
    {
        $this->officer();
        $this->complaintFromChat();

        $this->assertStringContainsString('Tulis jawabannya', $this->body($this->say('/jawab ADU-TANYA001')));
        $this->assertStringContainsString('Sertakan nomor tiketnya', $this->body($this->say('/jawab')));
    }

    public function test_answering_outside_the_assigned_categories_is_refused(): void
    {
        $this->officer(slug: 'jalan');
        $this->complaintFromChat();

        $reply = $this->body($this->say('/jawab ADU-TANYA001 Coba-coba.'));

        $this->assertStringContainsString('bukan kategori yang ditugaskan', $reply);
        $this->assertSame(0, DB::table('bot_outbox')->where('destination', '628121111222')->count());
    }

    public function test_a_recipient_without_the_tick_may_not_answer(): void
    {
        $this->officer(canCommand: false);
        $this->complaintFromChat();

        $reply = $this->body($this->say('/jawab ADU-TANYA001 Halo.'));

        // Menjawab adalah berbicara atas nama instansi kepada warga.
        $this->assertStringContainsString('tidak berwenang', $reply);
        $this->assertSame(0, DB::table('bot_outbox')->where('destination', '628121111222')->count());
    }

    /* ------------------------------------------------ bukan petugas */

    public function test_the_public_cannot_reach_the_officer_desk(): void
    {
        $this->officer();

        $warga = '628999888777';

        // Kewenangan datang dari baris bot_recipients tempat pesan itu tiba,
        // bukan dari isi pesannya. Nomor yang tidak terdaftar tidak pernah
        // menyentuh CommandHandler sama sekali.
        foreach (['/petugas', '/help', '/bantuan'] as $command) {
            $reply = $this->body($this->say($command, $warga));

            $this->assertStringNotContainsString('terdaftar sebagai petugas', $reply,
                "Warga mengetik {$command} seharusnya tidak melihat meja kerja petugas.");
            $this->assertStringNotContainsString('/proses', $reply);
        }
    }

    public function test_the_public_cannot_answer_somebody_elses_complaint(): void
    {
        $this->officer();
        $complaint = $this->complaintFromChat();

        $this->say('/jawab ADU-TANYA001 Pengaduan Anda ditolak.', '628999888777');

        // Tidak ada apa pun yang berangkat ke pelapor: seseorang yang bisa
        // menebak nomor tiket tidak boleh bisa berbicara atas nama instansi.
        $this->assertSame(0, DB::table('bot_outbox')->where('destination', '628121111222')->count());
        $this->assertCount(0, $complaint->fresh()->updates);
    }

    public function test_the_public_cannot_move_a_complaint(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        $this->say('/selesai ADU-TEST1234', '628999888777');

        $this->assertSame('baru', $complaint->fresh()->status);
    }

    public function test_a_deactivated_officer_is_treated_as_public_again(): void
    {
        $officer = $this->officer();
        $complaint = $this->complaint();

        $officer->update(['is_active' => false]);

        $reply = $this->body($this->say('/selesai ADU-TEST1234'));

        // Mencabut centang "Aktif" harus benar-benar mencabut kewenangannya,
        // bukan sekadar menghentikan kabar yang dikirim kepadanya.
        $this->assertStringNotContainsString('ditandai', $reply);
        $this->assertSame('baru', $complaint->fresh()->status);
    }

    /* ------------------------------------------- petugas sebagai warga */

    public function test_the_help_offers_the_way_into_the_public_menu(): void
    {
        $this->officer();

        $reply = $this->body($this->say('halo'));

        $this->assertStringContainsString('/menu', $reply);
        $this->assertStringContainsString('/petugas', $reply);
    }

    public function test_an_officer_may_open_the_public_menu_on_purpose(): void
    {
        $this->officer();

        $this->assertStringContainsString('Selamat datang', $this->body($this->say('/menu')));
    }

    public function test_once_inside_plain_messages_go_to_the_flow(): void
    {
        $this->officer();
        $this->say('/menu');

        // Tanpa ini petugas tersangkut pada pertanyaan pertama: pesan
        // berikutnya akan dijawab daftar perintah, bukan oleh alurnya.
        $reply = $this->body($this->say('1'));

        $this->assertStringContainsString('Jenis pengaduan', $reply);
    }

    public function test_an_officer_can_file_a_complaint_like_anybody_else(): void
    {
        $this->officer();

        $this->say('/menu');
        $this->say('1');
        $this->say((string) (ComplaintCategory::where('is_active', true)
            ->orderBy('sort_order')->pluck('slug')->search('lainnya') + 1));
        // "Pengaduan Lainnya" tidak mewajibkan bukti, jadi laporannya terbit
        // langsung dari uraiannya tanpa langkah foto.
        $reply = $this->body($this->say('Lampu jalan di depan kantor mati sejak pekan lalu.'));

        $complaint = Complaint::where('description', 'like', 'Lampu jalan%')->firstOrFail();

        $this->assertStringContainsString($complaint->ticket, $reply);
        $this->assertSame('lainnya', $complaint->category?->slug);
    }

    public function test_leaving_returns_to_the_officer_desk(): void
    {
        $this->officer();
        $this->say('/menu');

        $reply = $this->body($this->say('/petugas'));
        $this->assertStringContainsString('Percakapan warga ditutup', $reply);

        // Dan sesudahnya kembali ke meja kerja: pesan biasa tidak lagi
        // diteruskan ke alur warga, melainkan dibiarkan lewat begitu saja.
        $this->assertSame('', $this->body($this->say('halo')));
        $this->assertStringContainsString('terdaftar sebagai petugas', $this->body($this->say('/help')));
    }

    public function test_commands_still_work_while_the_public_menu_is_open(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        $this->say('/menu');
        $this->body($this->say('/proses ADU-TEST1234'));

        // Kabar pengaduan tidak berhenti hanya karena petugas sedang memakai
        // layanan sebagai warga.
        $this->assertSame('diproses', $complaint->fresh()->status);
    }
}
