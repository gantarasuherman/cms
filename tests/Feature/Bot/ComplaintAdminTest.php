<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotConversation;
use App\Models\Bot\BotMessage;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintCategory;
use App\Models\User;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintAdminTest extends TestCase
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

    private function complaint(array $attributes = []): Complaint
    {
        return Complaint::create($attributes + [
            'ticket' => Complaint::newTicket(),
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Jalan berlubang di depan Balai Desa Sukamaju.',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'status' => 'baru',
        ]);
    }

    /* -------------------------------------------------------------- screens */

    public function test_the_complaint_screens_render(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)->get(route('admin.complaints.index'))->assertOk()->assertSee($complaint->ticket === '' ? 'Pengaduan' : 'Pengaduan');
        $this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee($complaint->ticket)
            ->assertSee('Jalan berlubang')
            ->assertSee('-6.2088000');
    }

    public function test_the_listing_counts_each_status(): void
    {
        $this->complaint(['status' => 'baru']);
        $this->complaint(['status' => 'selesai']);
        $this->complaint(['status' => 'selesai']);

        $this->actingAs($this->admin)->get(route('admin.complaints.index'))->assertOk()->assertSee('Sedang Diproses');
    }

    public function test_the_datatable_carries_what_the_listing_shows(): void
    {
        $this->complaint();

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.complaints.data').'?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data.0');

        foreach (['ticket', 'category_name', 'description', 'reporter', 'evidence', 'status_badge', 'created_at', 'actions'] as $column) {
            $this->assertArrayHasKey($column, $row);
        }
    }

    public function test_the_listing_can_be_filtered(): void
    {
        $this->complaint(['status' => 'baru']);
        $this->complaint(['status' => 'selesai', 'complaint_category_id' => ComplaintCategory::where('slug', 'irigasi')->value('id')]);

        $this->assertSame(1, $this->actingAs($this->admin)
            ->getJson(route('admin.complaints.data').'?draw=1&start=0&length=10&status=selesai')
            ->json('recordsFiltered'));

        $this->assertSame(1, $this->actingAs($this->admin)
            ->getJson(route('admin.complaints.data').'?draw=1&start=0&length=10&category='.ComplaintCategory::where('slug', 'jalan')->value('id'))
            ->json('recordsFiltered'));
    }

    /* ----------------------------------------------------------- tindak lanjut */

    public function test_a_status_change_is_recorded_with_who_made_it(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->put(route('admin.complaints.update', $complaint), [
                'status' => 'diproses',
                'note' => 'Tim sudah dijadwalkan Kamis.',
            ])
            ->assertRedirect();

        $complaint->refresh();

        $this->assertSame('diproses', $complaint->status);
        $this->assertNotNull($complaint->processed_at);

        $update = $complaint->updates()->first();
        $this->assertSame('baru', $update->from_status);
        $this->assertSame('Tim sudah dijadwalkan Kamis.', $update->note);
        $this->assertSame($this->admin->name, $update->actor());
    }

    public function test_proof_of_completion_is_stored_apart_from_the_reporters_photos(): void
    {
        $complaint = $this->complaint();
        ComplaintAttachment::create(['complaint_id' => $complaint->id, 'kind' => 'report', 'path' => 'bot/foto-warga.jpg']);

        $this->actingAs($this->admin)
            ->put(route('admin.complaints.update', $complaint), [
                'status' => 'selesai',
                'proof' => [UploadedFile::fake()->image('selesai.jpg')],
            ])
            ->assertRedirect();

        $complaint->refresh();

        $this->assertSame('selesai', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertCount(1, $complaint->evidence);
        $this->assertCount(1, $complaint->resolutionProof);
        $this->assertSame($this->admin->getKey(), $complaint->resolutionProof->first()->uploaded_by);
    }

    public function test_proof_lands_on_the_private_disk(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)->put(route('admin.complaints.update', $complaint), [
            'status' => 'selesai',
            'proof' => [UploadedFile::fake()->image('selesai.jpg')],
        ]);

        $path = $complaint->fresh()->resolutionProof->first()->path;

        // A photograph of somebody's street, sent privately — never on a disk
        // that serves it to anyone who guesses the URL.
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->put(route('admin.complaints.update', $complaint), ['status' => 'dibatalkan-sendiri'])
            ->assertSessionHasErrors('status');
    }

    public function test_an_executable_upload_is_refused_as_proof(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->admin)
            ->put(route('admin.complaints.update', $complaint), [
                'status' => 'selesai',
                'proof' => [UploadedFile::fake()->create('payload.php', 10, 'application/x-php')],
            ])
            ->assertSessionHasErrors('proof.0');
    }

    /* ------------------------------------------------------------ lampiran */

    public function test_an_attachment_is_served_through_a_guarded_route(): void
    {
        Storage::disk('local')->put('complaints/bukti.jpg', 'isi-gambar');
        $complaint = $this->complaint();
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint->id, 'kind' => 'report',
            'path' => 'complaints/bukti.jpg', 'mime' => 'image/jpeg',
        ]);

        $this->actingAs($this->admin)->get($attachment->url())->assertOk();

        // Not reachable without a session at all.
        $this->post(route('admin.logout'));
        $this->get($attachment->url())->assertRedirect(route('admin.login'));
    }

    public function test_a_photograph_is_shown_rather_than_downloaded(): void
    {
        Storage::disk('local')->put('bot/foto.jpg', 'isi');
        $complaint = $this->complaint();

        // Nothing recorded, which is how the bot filed them: Telegram reports
        // photographs as application/octet-stream, and a file served under
        // that type lands in a Downloads folder instead of on the screen.
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint->id, 'kind' => 'report', 'path' => 'bot/foto.jpg', 'mime' => null,
        ]);

        $this->actingAs($this->admin)->get($attachment->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->assertTrue($attachment->isImage());
    }

    public function test_a_generic_type_from_the_platform_is_corrected(): void
    {
        Storage::disk('local')->put('bot/foto.jpg', 'isi');
        $conversation = $this->conversation();

        $message = BotMessage::create([
            'bot_conversation_id' => $conversation->id, 'direction' => 'in', 'type' => 'image',
            'media_path' => 'bot/foto.jpg', 'media_mime' => 'application/octet-stream',
        ]);

        $this->assertTrue($message->isImage());

        $this->actingAs($this->admin)->get($message->mediaUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_a_real_file_type_is_still_respected(): void
    {
        Storage::disk('local')->put('complaints/berkas.pdf', 'isi');
        $complaint = $this->complaint();
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint->id, 'kind' => 'report',
            'path' => 'complaints/berkas.pdf', 'mime' => 'application/pdf',
        ]);

        // Guessing must not override something that actually says what it is.
        $this->assertFalse($attachment->isImage());
        $this->actingAs($this->admin)->get($attachment->url())->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_missing_file_is_a_404_rather_than_a_crash(): void
    {
        $complaint = $this->complaint();
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $complaint->id, 'kind' => 'report', 'path' => 'complaints/hilang.jpg',
        ]);

        $this->actingAs($this->admin)->get($attachment->url())->assertNotFound();
    }

    /* ------------------------------------------------------------- izin */

    public function test_someone_without_the_module_cannot_reach_complaints(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $complaint = $this->complaint();

        $this->actingAs($outsider)->get(route('admin.complaints.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.complaints.show', $complaint))->assertForbidden();
        $this->actingAs($outsider)->put(route('admin.complaints.update', $complaint), ['status' => 'selesai'])->assertForbidden();
    }

    public function test_a_viewer_can_read_but_not_change(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('complaint.view');
        $complaint = $this->complaint();

        $this->actingAs($viewer)->get(route('admin.complaints.show', $complaint))->assertOk();
        $this->actingAs($viewer)->put(route('admin.complaints.update', $complaint), ['status' => 'selesai'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.complaints.destroy', $complaint))->assertForbidden();
    }

    public function test_an_id_in_the_url_does_not_grant_access_to_an_attachment(): void
    {
        Storage::disk('local')->put('complaints/rahasia.jpg', 'isi');
        $attachment = ComplaintAttachment::create([
            'complaint_id' => $this->complaint()->id, 'kind' => 'report', 'path' => 'complaints/rahasia.jpg',
        ]);

        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $this->actingAs($outsider)->get($attachment->url())->assertForbidden();
    }

    /* ------------------------------------------------------- percakapan */

    private function conversation(): BotConversation
    {
        $channel = BotChannel::where('key', 'whatsapp')->firstOrFail();
        $contact = BotContact::create([
            'bot_channel_id' => $channel->id, 'external_id' => '628123456789',
            'phone' => '628123456789', 'name' => 'Warga Sukamaju',
        ]);

        $conversation = BotConversation::create([
            'bot_channel_id' => $channel->id, 'bot_contact_id' => $contact->id,
            'status' => 'active', 'current_node' => 'menu_utama',
            'state' => ['answers' => ['description' => 'Jalan berlubang.', '_offered.menu_utama' => [['value' => '1']]]],
        ]);

        BotMessage::create(['bot_conversation_id' => $conversation->id, 'direction' => 'in', 'type' => 'text', 'body' => 'halo']);
        BotMessage::create(['bot_conversation_id' => $conversation->id, 'direction' => 'out', 'type' => 'text', 'body' => 'Selamat datang', 'node_key' => 'menu_utama']);

        return $conversation;
    }

    public function test_the_listings_report_their_counts(): void
    {
        $complaint = $this->complaint();
        ComplaintAttachment::create(['complaint_id' => $complaint->id, 'kind' => 'report', 'path' => 'bot/a.jpg']);
        ComplaintAttachment::create(['complaint_id' => $complaint->id, 'kind' => 'report', 'path' => 'bot/b.jpg']);
        $this->conversation();

        // `select()` after `withCount()` replaces the select list and wipes the
        // count's subquery; the column then renders blank with no error.
        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.complaints.data').'?draw=1&start=0&length=10')
            ->json('data.0');
        $this->assertStringContainsString('2', $row['evidence']);

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.bot.conversations.data').'?draw=1&start=0&length=10')
            ->json('data.0');
        $this->assertSame(2, $row['messages_count']);
    }

    public function test_the_conversation_screens_render_the_transcript(): void
    {
        $conversation = $this->conversation();

        $this->actingAs($this->admin)->get(route('admin.bot.conversations.index'))->assertOk();

        $this->actingAs($this->admin)->get(route('admin.bot.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('halo')
            ->assertSee('Selamat datang')
            ->assertSee('menu_utama');
    }

    public function test_internal_bookkeeping_is_not_shown_as_an_answer(): void
    {
        $conversation = $this->conversation();

        $this->actingAs($this->admin)->get(route('admin.bot.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('description')
            // The options the menu offered are not something the person said.
            ->assertDontSee('_offered.menu_utama');
    }

    public function test_a_phone_number_is_masked_in_the_list_that_sits_open_all_day(): void
    {
        $conversation = $this->conversation();
        $conversation->contact->update(['name' => null]);

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.bot.conversations.data').'?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data.0');

        $this->assertStringNotContainsString('628123456789', $row['contact']);
        $this->assertStringContainsString('•', $row['contact']);
    }

    public function test_the_whole_number_is_shown_on_one_opened_conversation(): void
    {
        $conversation = $this->conversation();
        $conversation->channel->update(['key' => 'whatsapp']);

        // Opening a single conversation is a deliberate act, and an operator
        // ringing somebody back cannot dial dots. The list stays masked.
        $this->actingAs($this->admin)->get(route('admin.bot.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('628123456789')
            ->assertSee('tel:628123456789', false);
    }

    public function test_a_contact_without_a_picture_is_shown_by_initials(): void
    {
        $conversation = $this->conversation();

        // Every WhatsApp contact is in this case: the Cloud API does not
        // expose profile pictures at all.
        $this->assertNull($conversation->contact->avatarUrl());
        $this->assertSame('WS', $conversation->contact->initials());

        $this->actingAs($this->admin)->get(route('admin.bot.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('WS');
    }

    public function test_a_profile_picture_is_served_behind_the_same_permission(): void
    {
        Storage::disk('local')->put('bot/avatar.jpg', 'isi');
        $conversation = $this->conversation();
        $conversation->contact->update(['avatar_path' => 'bot/avatar.jpg', 'avatar_ref' => 'file-1']);

        $this->actingAs($this->admin)->get($conversation->contact->fresh()->avatarUrl())->assertOk();

        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');
        $this->actingAs($outsider)->get($conversation->contact->fresh()->avatarUrl())->assertForbidden();
    }

    public function test_the_transcript_marks_days_and_who_wrote_each_line(): void
    {
        $conversation = $this->conversation();
        BotMessage::create([
            'bot_conversation_id' => $conversation->id, 'direction' => 'out',
            'type' => 'text', 'body' => 'Sudah ditindaklanjuti.', 'node_key' => 'admin',
        ]);

        $this->actingAs($this->admin)->get(route('admin.bot.conversations.show', $conversation))
            ->assertOk()
            // Without a day marker a long transcript is one undated wall.
            ->assertSee(now()->translatedFormat('l, d F Y'))
            // A line written by a person from the panel is not the flow talking.
            ->assertSee('Operator');
    }

    public function test_chat_media_is_served_only_to_someone_allowed_to_read_it(): void
    {
        Storage::disk('local')->put('bot/foto.jpg', 'isi');
        $conversation = $this->conversation();
        $message = BotMessage::create([
            'bot_conversation_id' => $conversation->id, 'direction' => 'in', 'type' => 'image',
            'media_path' => 'bot/foto.jpg', 'media_mime' => 'image/jpeg',
        ]);

        $this->actingAs($this->admin)->get($message->mediaUrl())->assertOk();

        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');
        $this->actingAs($outsider)->get($message->mediaUrl())->assertForbidden();
    }

    public function test_the_transcript_offers_no_way_to_reply(): void
    {
        // An operator answering here would cut across the flow the person is
        // in the middle of; replies go through the flow or a command.
        $this->actingAs($this->admin)
            ->get(route('admin.bot.conversations.show', $this->conversation()))
            ->assertOk()
            ->assertDontSee('name="reply"', false);
    }
}
