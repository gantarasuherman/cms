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
use Tests\TestCase;

class BotApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'rahasia-internal';

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

    private function inbound(array $payload = [], ?string $token = self::TOKEN): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($token === null ? [] : ['X-Bot-Token' => $token])
            ->postJson(route('api.bot.inbound'), $payload + [
                'channel' => 'whatsapp',
                'external_id' => 'wamid.'.(++$this->counter),
                'from' => '628120000001',
                'type' => 'text',
                'text' => 'halo',
            ]);
    }

    /* ------------------------------------------------------------ the gate */

    public function test_the_internal_api_is_closed_without_the_secret(): void
    {
        $this->inbound([], null)->assertUnauthorized();
        $this->inbound([], 'salah')->assertUnauthorized();
    }

    public function test_an_unconfigured_secret_fails_shut_rather_than_open(): void
    {
        // Otherwise a deployment that forgot to set it would be wide open.
        config(['bot.internal_token' => null]);

        $this->inbound([], '')->assertUnauthorized();
        $this->inbound()->assertUnauthorized();
    }

    public function test_the_master_switch_closes_the_endpoint(): void
    {
        config(['bot.enabled' => false]);

        $this->inbound()->assertStatus(503);
    }

    public function test_the_internal_api_is_not_reachable_from_the_public_docs(): void
    {
        // The public API is read-only by design; the bot routes must not have
        // quietly widened it.
        foreach (['api/public/news', 'api/public/home'] as $path) {
            $this->postJson($path)->assertStatus(405);
        }
    }

    /* ------------------------------------------------------------- inbound */

    public function test_a_message_runs_the_flow_and_returns_the_replies(): void
    {
        $response = $this->inbound()->assertOk();

        $response->assertJsonPath('status', 'handled');
        $this->assertStringContainsString('1. Pengaduan', $response->json('messages.0.body'));
    }

    public function test_a_replayed_delivery_is_handled_once(): void
    {
        $id = 'wamid.SAMA';

        $first = $this->inbound(['external_id' => $id])->assertOk();
        $second = $this->inbound(['external_id' => $id])->assertOk();

        $this->assertSame('handled', $first->json('status'));
        // Both platforms retry a delivery they believe failed; without this one
        // report would be filed twice.
        $this->assertSame('duplicate', $second->json('status'));
        $this->assertSame([], $second->json('messages'));
        $this->assertSame(1, DB::table('bot_webhook_events')->count());
    }

    public function test_an_inactive_channel_answers_politely_and_does_nothing(): void
    {
        BotChannel::where('key', 'whatsapp')->update(['is_active' => false]);

        $this->inbound()->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSame(0, DB::table('bot_conversations')->count());
    }

    public function test_a_malformed_payload_is_refused(): void
    {
        $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.inbound'), ['channel' => 'friendster', 'external_id' => 'x', 'from' => 'y'])
            ->assertStatus(422);

        $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.inbound'), [
                'channel' => 'whatsapp', 'external_id' => 'x', 'from' => 'y',
                'type' => 'location', 'latitude' => 999, 'longitude' => 0,
            ])
            ->assertStatus(422);
    }

    /* ------------------------------------------------------------ commands */

    private function officer(array $categories = ['jalan'], bool $canCommand = true): BotRecipient
    {
        $recipient = BotRecipient::create([
            'name' => 'Admin Jalan', 'channel' => 'whatsapp',
            'destination' => '628129999999', 'is_active' => true, 'can_command' => $canCommand,
        ]);

        foreach ($categories as $slug) {
            $recipient->categories()->attach(ComplaintCategory::where('slug', $slug)->value('id'));
        }

        return $recipient;
    }

    private function complaint(string $slug = 'jalan'): Complaint
    {
        return Complaint::create([
            'ticket' => Complaint::newTicket(),
            'complaint_category_id' => ComplaintCategory::where('slug', $slug)->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Jalan berlubang.',
            'status' => 'baru',
        ]);
    }

    public function test_an_officer_can_move_a_complaint_with_a_command(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        $response = $this->inbound([
            'from' => '628129999999',
            'text' => '/proses '.$complaint->ticket,
        ])->assertOk();

        $this->assertSame('command', $response->json('status'));
        $this->assertSame('diproses', $complaint->fresh()->status);
        $this->assertNotNull($complaint->fresh()->processed_at);
    }

    public function test_finishing_a_complaint_keeps_the_photo_as_proof_of_the_work(): void
    {
        $this->officer();
        $complaint = $this->complaint();

        $this->inbound([
            'from' => '628129999999',
            'type' => 'image',
            'text' => '/selesai '.$complaint->ticket.' sudah ditambal pagi ini',
            'media_path' => 'bot/bukti-selesai.jpg',
        ])->assertOk();

        $complaint->refresh();

        $this->assertSame('selesai', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);
        // Proof of completion, not a second copy of the original report.
        $this->assertCount(1, $complaint->resolutionProof);
        $this->assertCount(0, $complaint->evidence);
        $this->assertSame('sudah ditambal pagi ini', $complaint->updates()->first()->note);
    }

    public function test_a_destination_that_was_not_given_authority_cannot_command(): void
    {
        $this->officer(canCommand: false);
        $complaint = $this->complaint();

        $response = $this->inbound(['from' => '628129999999', 'text' => '/selesai '.$complaint->ticket]);

        $this->assertStringContainsString('tidak berwenang', $response->json('messages.0.body'));
        $this->assertSame('baru', $complaint->fresh()->status);
    }

    public function test_an_officer_cannot_touch_a_category_they_do_not_cover(): void
    {
        $this->officer(['jalan']);
        $complaint = $this->complaint('irigasi');

        $response = $this->inbound(['from' => '628129999999', 'text' => '/selesai '.$complaint->ticket]);

        // Being told about a road defect must not let someone close an
        // irrigation report they were never shown.
        $this->assertStringContainsString('bukan kategori yang ditugaskan', $response->json('messages.0.body'));
        $this->assertSame('baru', $complaint->fresh()->status);
    }

    public function test_a_command_from_the_public_is_treated_as_ordinary_conversation(): void
    {
        $complaint = $this->complaint();

        // A member of the public typing /selesai is not an officer.
        $this->inbound(['from' => '628120000055', 'text' => '/selesai '.$complaint->ticket])->assertOk();

        $this->assertSame('baru', $complaint->fresh()->status);
    }

    public function test_a_command_without_a_ticket_asks_for_one(): void
    {
        $this->officer();

        $response = $this->inbound(['from' => '628129999999', 'text' => '/selesai']);

        $this->assertStringContainsString('Sertakan nomor tiketnya', $response->json('messages.0.body'));
    }

    public function test_an_unknown_ticket_is_reported_without_changing_anything(): void
    {
        $this->officer();

        $response = $this->inbound(['from' => '628129999999', 'text' => '/proses ADU-ZZZZZZZZ']);

        $this->assertStringContainsString('tidak ditemukan', $response->json('messages.0.body'));
    }

    /* ------------------------------------------------------------- outbox */

    private function queue(int $count = 1, string $channel = 'whatsapp'): void
    {
        foreach (range(1, $count) as $i) {
            DB::table('bot_outbox')->insert([
                'channel' => $channel, 'destination' => '62812000'.$i, 'type' => 'text',
                'body' => 'Pengaduan baru '.$i, 'status' => 'pending', 'attempts' => 0,
                'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function pull(array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.outbox.pull'), $payload);
    }

    public function test_pending_messages_are_pulled_and_claimed(): void
    {
        $this->queue(2);

        $this->pull()->assertOk()->assertJsonCount(2, 'messages');

        // Claimed in the same breath, so a second worker cannot send them too.
        $this->pull()->assertOk()->assertJsonCount(0, 'messages');
        $this->assertSame(2, DB::table('bot_outbox')->where('status', 'sending')->count());
    }

    public function test_a_pull_can_be_limited_to_one_channel(): void
    {
        $this->queue(1, 'whatsapp');
        $this->queue(1, 'telegram');

        $this->pull(['channel' => 'telegram'])
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.channel', 'telegram');
    }

    public function test_a_delivered_message_is_marked_sent(): void
    {
        $this->queue();
        $id = $this->pull()->json('messages.0.id');

        $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.outbox.report'), ['id' => $id, 'status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $row = DB::table('bot_outbox')->find($id);
        $this->assertSame('sent', $row->status);
        $this->assertNotNull($row->sent_at);
    }

    public function test_a_failed_send_goes_back_in_the_queue_with_a_delay(): void
    {
        $this->queue();
        $id = $this->pull()->json('messages.0.id');

        $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.outbox.report'), ['id' => $id, 'status' => 'failed', 'error' => 'Meta 503'])
            ->assertOk()
            ->assertJsonPath('status', 'retry');

        $row = DB::table('bot_outbox')->find($id);

        $this->assertSame('pending', $row->status);
        $this->assertSame('Meta 503', $row->last_error);
        // Waiting rather than hammering a platform that is refusing.
        $this->assertTrue(now()->lt($row->available_at));
    }

    public function test_a_message_that_keeps_failing_is_given_up_on_and_says_why(): void
    {
        $this->queue();

        for ($i = 0; $i < 5; $i++) {
            DB::table('bot_outbox')->update(['status' => 'pending', 'available_at' => now()->subMinute()]);
            $id = $this->pull()->json('messages.0.id');
            $this->withHeaders(['X-Bot-Token' => self::TOKEN])
                ->postJson(route('api.bot.outbox.report'), ['id' => $id, 'status' => 'failed', 'error' => 'ditolak']);
        }

        $row = DB::table('bot_outbox')->first();

        $this->assertSame('failed', $row->status);
        $this->assertSame('ditolak', $row->last_error);
        // Not retried forever, but the reason is on the record.
        $this->assertSame(0, $this->pull()->json('messages') === [] ? 0 : 1);
    }

    public function test_reporting_an_unknown_message_is_a_404_not_a_crash(): void
    {
        $this->withHeaders(['X-Bot-Token' => self::TOKEN])
            ->postJson(route('api.bot.outbox.report'), ['id' => 999999, 'status' => 'sent'])
            ->assertNotFound();
    }

    public function test_the_outbox_is_behind_the_same_gate(): void
    {
        $this->postJson(route('api.bot.outbox.pull'))->assertUnauthorized();
        $this->postJson(route('api.bot.outbox.report'), ['id' => 1, 'status' => 'sent'])->assertUnauthorized();
    }

    /* ------------------------------------------------------- end to end */

    public function test_a_complaint_filed_by_chat_reaches_the_officers_queue(): void
    {
        $this->officer();

        $from = '628120000123';
        $say = fn (array $extra) => $this->inbound($extra + ['from' => $from]);

        $say(['text' => 'halo']);
        $say(['text' => '1']);
        $say(['text' => '1']);
        $say(['text' => 'Jalan berlubang di depan Balai Desa Sukamaju.']);
        $say(['type' => 'image', 'text' => null, 'media_path' => 'bot/foto.jpg']);
        $say(['type' => 'location', 'text' => null, 'latitude' => -6.2, 'longitude' => 106.8]);

        $complaint = Complaint::firstOrFail();
        $queued = $this->pull()->json('messages');

        $this->assertCount(1, $queued);
        $this->assertSame('628129999999', $queued[0]['destination']);
        $this->assertStringContainsString($complaint->ticket, $queued[0]['body']);

        // And the officer can close it from the same message they received.
        $this->inbound(['from' => '628129999999', 'text' => '/selesai '.$complaint->ticket]);

        $this->assertSame('selesai', $complaint->fresh()->status);
    }
}
