<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotRecipient;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\DispositionTarget;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Triase petugas: apakah ini kewenangan kami, dan bila bukan, ke mana.
 *
 * Yang paling perlu dijaga di sini bukan alurnya, melainkan dua pesan yang
 * keluar: meneruskan tanpa memberi tahu pelapor membuat laporannya tampak
 * hilang begitu saja, dan menandainya "selesai" membuat pekerjaan yang
 * berpindah tangan tampak sudah dikerjakan.
 */
class ComplaintTriageTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'rahasia-internal';

    private const OFFICER = '628120000777';

    private const REPORTER = '628121111222';

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

    private function officer(bool $canCommand = true): BotRecipient
    {
        return BotRecipient::create([
            'name' => 'Petugas Piket', 'channel' => 'whatsapp',
            'destination' => self::OFFICER, 'is_active' => true, 'can_command' => $canCommand,
        ]);
    }

    private function target(
        string $name = 'Dinas Perhubungan',
        string $phone = '628111222333',
        string $channel = 'link',
    ): DispositionTarget {
        return DispositionTarget::create([
            'name' => $name, 'slug' => str($name)->slug()->value(),
            'phone' => $phone, 'channel' => $channel, 'sort_order' => 10, 'is_active' => true,
        ]);
    }

    /** Dua langkah: nyatakan bukan kewenangan kita, lalu pilih instansinya. */
    private function forwardTo(DispositionTarget $target): string
    {
        $this->say('/bukan ADU-TRIASE01');

        return $this->body($this->say('1'));
    }

    private function complaint(): Complaint
    {
        $channel = BotChannel::where('key', 'whatsapp')->firstOrFail();

        $contact = BotContact::create([
            'bot_channel_id' => $channel->getKey(),
            'external_id' => self::REPORTER,
            'name' => 'Warga Sukamaju',
        ]);

        return Complaint::create([
            'ticket' => 'ADU-TRIASE01',
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'bot_contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'description' => 'Lampu lalu lintas di simpang mati.',
            'status' => 'baru',
        ]);
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

    /* ------------------------------------------------------------- alur */

    public function test_the_command_goes_straight_to_the_list_of_agencies(): void
    {
        $this->officer();
        $this->target();
        $this->complaint();

        $reply = $this->body($this->say('/bukan ADU-TRIASE01'));

        // Satu pertanyaan, bukan tiga: kewenangannya sudah dinyatakan oleh
        // perintah yang diketik, dan penolakan punya perintahnya sendiri.
        $this->assertStringContainsString('Arahkan warga ke mana?', $reply);
        $this->assertStringContainsString('1. Dinas Perhubungan', $reply);
        $this->assertStringNotContainsString('Apakah ini kewenangan', $reply);
    }

    public function test_the_old_name_still_works(): void
    {
        $this->officer();
        $this->target();
        $this->complaint();

        // Perintah yang pernah diajarkan lalu diam-diam dihapus hanya
        // menyisakan orang yang mengira botnya rusak.
        $this->assertStringContainsString(
            'Arahkan warga ke mana?',
            $this->body($this->say('/triase ADU-TRIASE01')),
        );
    }

    public function test_nothing_changes_until_an_agency_is_chosen(): void
    {
        $this->officer();
        $this->target();
        $complaint = $this->complaint();

        $this->say('/bukan ADU-TRIASE01');

        $this->assertSame('baru', $complaint->fresh()->status);
        $this->assertSame(0, DB::table('bot_outbox')->count());
    }

    public function test_each_command_is_offered_as_its_own_copyable_block(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        app(\App\Services\Bot\Actions\OfficerNotifier::class)->notify($complaint);

        $body = DB::table('bot_outbox')->where('destination', self::OFFICER)->value('body');

        // Satu blok per perintah: Telegram menyalin seluruh isi sebuah blok
        // dengan satu ketukan, jadi tiga perintah dalam satu blok akan
        // menyalin ketiganya sekaligus.
        foreach (['/bukan', '/proses', '/selesai'] as $command) {
            $this->assertStringContainsString(
                "```\n".$command.' ADU-TRIASE01'."\n```",
                $body,
                "Perintah {$command} seharusnya berdiri di bloknya sendiri.",
            );
        }
    }

    /* ------------------------------------------------------- pengarahan */

    public function test_the_reporter_is_told_where_to_go_and_given_the_number(): void
    {
        $this->officer();
        $target = $this->target();
        $this->complaint();

        $reply = $this->forwardTo($target);

        $this->assertStringContainsString('Dinas Perhubungan', $reply);
        $this->assertStringContainsString('628111222333', $reply);

        $toReporter = DB::table('bot_outbox')->where('destination', self::REPORTER)->first();

        $this->assertNotNull($toReporter);
        $this->assertStringContainsString('Dinas Perhubungan', $toReporter->body);
        // Nomornya, bukan sekadar namanya: warga yang diberi tahu "bukan
        // kewenangan kami" tanpa nomor tujuan tetap berakhir buntu.
        $this->assertStringContainsString('628111222333', $toReporter->body);
    }

    public function test_the_bot_never_contacts_the_other_agency(): void
    {
        $this->officer();
        $target = $this->target();
        $this->complaint();

        $this->forwardTo($target);

        // Menghubungi instansi lain atas nama warga menjanjikan sesuatu yang
        // tidak dapat dijamin dinas ini — dan itulah yang menghindarkan biaya
        // WhatsApp, template Meta, serta jendela 24 jam sekaligus.
        $this->assertSame(0, DB::table('bot_outbox')->where('destination', $target->phone)->count());
        $this->assertSame(1, DB::table('bot_outbox')->count());
    }

    public function test_the_message_to_the_reporter_can_be_reworded(): void
    {
        $this->officer();
        $target = $this->target();
        $target->update([
            'reporter_template' => 'Tiket {ticket}: silakan hubungi {target} di {target_phone}.',
        ]);
        $this->complaint();

        $this->forwardTo($target);

        $toReporter = DB::table('bot_outbox')->where('destination', self::REPORTER)->firstOrFail();

        $this->assertSame(
            'Tiket ADU-TRIASE01: silakan hubungi Dinas Perhubungan di 628111222333.',
            $toReporter->body,
        );
    }

    public function test_the_contact_person_is_named_when_there_is_one(): void
    {
        $this->officer();
        $target = $this->target();
        $target->update(['contact_person' => 'Pak Budi']);
        $this->complaint();

        $this->forwardTo($target);

        $toReporter = DB::table('bot_outbox')->where('destination', self::REPORTER)->firstOrFail();

        $this->assertStringContainsString('Pak Budi', $toReporter->body);
    }

    public function test_forwarding_is_not_the_same_as_finishing(): void
    {
        $this->officer();
        $this->target();
        $complaint = $this->complaint();

        $this->say('/bukan ADU-TRIASE01');
        $this->say('1');

        // Pekerjaannya berpindah tangan, belum tentu dikerjakan. Menandainya
        // selesai membuat laporan yang mungkin mangkrak di instansi lain
        // hilang dari pandangan.
        $this->assertSame('diteruskan', $complaint->fresh()->status);
    }

    public function test_the_destination_is_copied_not_merely_referenced(): void
    {
        $this->officer();
        $target = $this->target();
        $this->complaint();

        $this->say('/bukan ADU-TRIASE01');
        $this->say('1');

        $target->delete();

        // Instansi boleh dihapus dari daftar; riwayat tetap harus menjawab
        // "waktu itu dikirim ke mana".
        $this->assertDatabaseHas('complaint_dispositions', [
            'target_name' => 'Dinas Perhubungan',
            'target_phone' => '628111222333',
        ]);
    }

    public function test_forwarding_with_no_destinations_says_so(): void
    {
        $this->officer();
        $this->complaint();

        $reply = $this->body($this->say('/bukan ADU-TRIASE01'));

        // Tanpa satu pun tujuan terdaftar, tidak ada menu untuk dibuka — dan
        // petugas diarahkan ke /tolak, bukan dibiarkan menunggu.
        $this->assertStringContainsString('Belum ada instansi tujuan', $reply);
        $this->assertStringContainsString('/tolak', $reply);
        $this->assertDatabaseCount('officer_triages', 0);
    }

    /* ------------------------------------------------------------ jaga */

    public function test_a_command_still_works_in_the_middle_of_a_triage(): void
    {
        $this->officer();
        $target = $this->target();
        $complaint = $this->complaint();

        $this->say('/bukan ADU-TRIASE01');

        // Menyela dengan perintah lain tidak boleh menghilangkan tempatnya.
        $this->assertStringContainsString('ADU-TRIASE01', $this->body($this->say('/info ADU-TRIASE01')));
        $this->assertStringContainsString('diarahkan ke', $this->body($this->say('1')));
        $this->assertSame('diteruskan', $complaint->fresh()->status);
    }

    public function test_an_answer_outside_the_menu_is_left_alone(): void
    {
        $this->officer();
        $this->target();
        $this->complaint();

        $this->say('/bukan ADU-TRIASE01');

        // Angka yang tidak ditawarkan bukan jawaban; petugas mendapat daftar
        // perintah, dan triasenya tetap menunggu.
        $this->assertStringContainsString('terdaftar sebagai petugas', $this->body($this->say('9')));
        $this->assertDatabaseCount('officer_triages', 1);
    }

    public function test_the_triage_can_be_abandoned(): void
    {
        $this->officer();
        $this->target();
        $complaint = $this->complaint();

        $this->say('/bukan ADU-TRIASE01');
        $reply = $this->body($this->say('/batal'));

        $this->assertStringContainsString('dihentikan', $reply);
        $this->assertSame('baru', $complaint->fresh()->status);
        $this->assertDatabaseCount('officer_triages', 0);
    }

    public function test_a_recipient_without_the_tick_may_not_redirect(): void
    {
        $this->officer(canCommand: false);
        $this->complaint();

        $this->assertStringContainsString('tidak berwenang', $this->body($this->say('/bukan ADU-TRIASE01')));
        $this->assertDatabaseCount('officer_triages', 0);
    }

    public function test_the_public_cannot_redirect_anything(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        $this->say('/bukan ADU-TRIASE01', '628129999999');

        $this->assertDatabaseCount('officer_triages', 0);
        $this->assertSame('baru', $complaint->fresh()->status);
    }
}
