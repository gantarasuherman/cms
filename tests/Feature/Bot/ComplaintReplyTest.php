<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotMessage;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    /** A complaint that arrived through Telegram, with its conversation. */
    private function fromChat(): Complaint
    {
        $channel = BotChannel::where('key', 'telegram')->firstOrFail();
        $contact = BotContact::create([
            'bot_channel_id' => $channel->id, 'external_id' => '-100777', 'name' => 'Warga Sukamaju',
        ]);
        $conversation = BotConversation::create([
            'bot_channel_id' => $channel->id, 'bot_contact_id' => $contact->id, 'status' => 'completed',
        ]);

        return Complaint::create([
            'ticket' => Complaint::newTicket(),
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'bot_contact_id' => $contact->id,
            'bot_conversation_id' => $conversation->id,
            'channel' => 'telegram',
            'description' => 'Jalan berlubang.',
            'status' => 'baru',
        ]);
    }

    private function fromNowhere(): Complaint
    {
        return Complaint::create([
            'ticket' => Complaint::newTicket(),
            'channel' => 'web', 'description' => 'Dicatat petugas dari telepon.', 'status' => 'baru',
        ]);
    }

    /* --------------------------------------------------------- membalas */

    public function test_an_operator_can_write_back_from_the_panel(): void
    {
        $complaint = $this->fromChat();

        $this->actingAs($this->admin)
            ->post(route('admin.complaints.reply', $complaint), [
                'message' => 'Tim kami dijadwalkan meninjau lokasi Kamis pagi.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $queued = DB::table('bot_outbox')->get();

        // Queued, not sent inline: the request must not fail because a platform
        // was briefly unreachable.
        $this->assertCount(1, $queued);
        $this->assertSame('telegram', $queued->first()->channel);
        $this->assertSame('-100777', $queued->first()->destination);
        $this->assertStringContainsString('Kamis pagi', $queued->first()->body);
    }

    public function test_the_reply_joins_the_conversation_transcript(): void
    {
        $complaint = $this->fromChat();

        $this->actingAs($this->admin)->post(route('admin.complaints.reply', $complaint), [
            'message' => 'Sudah kami tindak lanjuti.',
        ]);

        $message = BotMessage::where('bot_conversation_id', $complaint->bot_conversation_id)->firstOrFail();

        // So the conversation reads as one exchange rather than the reporter's
        // half of it.
        $this->assertSame('out', $message->direction);
        $this->assertSame('admin', $message->node_key);
        $this->assertSame('Sudah kami tindak lanjuti.', $message->body);
    }

    public function test_the_reply_is_recorded_against_the_complaint(): void
    {
        $complaint = $this->fromChat();

        $this->actingAs($this->admin)->post(route('admin.complaints.reply', $complaint), [
            'message' => 'Mohon ditunggu.',
        ]);

        $update = $complaint->updates()->firstOrFail();

        $this->assertSame('Mohon ditunggu.', $update->note);
        $this->assertSame($this->admin->getKey(), $update->user_id);
        $this->assertSame($this->admin->name, $update->actor());
    }

    public function test_a_photo_can_be_sent_with_the_reply(): void
    {
        $complaint = $this->fromChat();

        $this->actingAs($this->admin)->post(route('admin.complaints.reply', $complaint), [
            'message' => 'Sudah selesai ditambal.',
            'attachment' => UploadedFile::fake()->image('hasil.jpg'),
        ]);

        $queued = DB::table('bot_outbox')->first();

        $this->assertSame('image', $queued->type);
        $this->assertNotNull($queued->media_path);
        // Private disk: a photograph of somebody's street is not public.
        Storage::disk('local')->assertExists($queued->media_path);
        Storage::disk('public')->assertMissing($queued->media_path);
    }

    public function test_an_empty_message_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.complaints.reply', $this->fromChat()), ['message' => ''])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, DB::table('bot_outbox')->count());
    }

    public function test_a_complaint_with_nowhere_to_reply_says_so(): void
    {
        $complaint = $this->fromNowhere();

        $this->actingAs($this->admin)
            ->post(route('admin.complaints.reply', $complaint), ['message' => 'Halo'])
            ->assertSessionHas('warning');

        // Better than a form that fails on submit.
        $this->assertSame(0, DB::table('bot_outbox')->count());

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('tidak ada tujuan')
            ->assertDontSee('name="message"', false);
    }

    /* ------------------------------------------------- kabar perubahan */

    public function test_a_status_change_can_tell_the_reporter(): void
    {
        $complaint = $this->fromChat();

        $this->actingAs($this->admin)->put(route('admin.complaints.update', $complaint), [
            'status' => 'diproses',
            'note' => 'Dijadwalkan Kamis.',
            'notify_reporter' => 1,
        ])->assertRedirect();

        $queued = DB::table('bot_outbox')->firstOrFail();

        $this->assertStringContainsString($complaint->ticket, $queued->body);
        $this->assertStringContainsString('Sedang Diproses', $queued->body);
        $this->assertStringContainsString('Dijadwalkan Kamis.', $queued->body);
    }

    public function test_a_status_change_stays_silent_when_not_asked(): void
    {
        $complaint = $this->fromChat();

        $this->actingAs($this->admin)->put(route('admin.complaints.update', $complaint), [
            'status' => 'diproses',
        ])->assertRedirect();

        // Not every internal step is worth a message on somebody's phone.
        $this->assertSame(0, DB::table('bot_outbox')->count());
        $this->assertSame('diproses', $complaint->fresh()->status);
    }

    public function test_notifying_an_unreachable_reporter_is_reported_not_hidden(): void
    {
        $complaint = $this->fromNowhere();

        $this->actingAs($this->admin)->put(route('admin.complaints.update', $complaint), [
            'status' => 'selesai', 'notify_reporter' => 1,
        ])->assertRedirect();

        // The status still changed; the operator is told the message went
        // nowhere rather than assuming it arrived.
        $this->assertSame('selesai', $complaint->fresh()->status);
        $this->assertSame(0, DB::table('bot_outbox')->count());
        $this->assertStringContainsString('tidak dapat dihubungi', session('success'));
    }

    /* ---------------------------------------------------------------- izin */

    public function test_a_viewer_cannot_reply(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('complaint.view');

        $this->actingAs($viewer)
            ->post(route('admin.complaints.reply', $this->fromChat()), ['message' => 'Halo'])
            ->assertForbidden();

        $this->assertSame(0, DB::table('bot_outbox')->count());
    }

    public function test_someone_without_the_module_cannot_reply(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $this->actingAs($outsider)
            ->post(route('admin.complaints.reply', $this->fromChat()), ['message' => 'Halo'])
            ->assertForbidden();
    }

    /* ------------------------------------------------------ transkrip utuh */

    public function test_the_transcript_still_offers_no_reply_box_of_its_own(): void
    {
        $complaint = $this->fromChat();

        // Replying about a filed complaint is out-of-band and expected. Typing
        // into the live transcript would cut across whatever the flow is in the
        // middle of asking, which is why that screen still has no box.
        $this->actingAs($this->admin)
            ->get(route('admin.bot.conversations.show', $complaint->bot_conversation_id))
            ->assertOk()
            ->assertDontSee('name="message"', false);
    }
}
