<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotChannel;
use App\Models\Bot\BotContact;
use App\Models\Bot\BotMessage;
use App\Models\Complaint;
use App\Models\User;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotSimulatorTest extends TestCase
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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        BotChannel::where('key', 'telegram')->update(['is_active' => true]);
    }

    private function send(string $text): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bot.simulator.send'), ['channel' => 'telegram', 'text' => $text])
            ->assertRedirect();
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_screen_renders_with_readiness_checks(): void
    {
        $this->actingAs($this->admin)->get(route('admin.bot.simulator.index'))
            ->assertOk()
            ->assertSee('Kesiapan Konfigurasi')
            // Tanpa petugas penerima, layar harus mengatakan akibatnya.
            ->assertSee('tidak ada yang dikabari');
    }

    /* ---------------------------------------------------------- percakapan */

    public function test_a_message_gets_the_real_bot_reply(): void
    {
        $this->send('menu');

        $this->actingAs($this->admin)->get(route('admin.bot.simulator.index', ['channel' => 'telegram']))
            ->assertOk()
            // Sapaan menu utama berasal dari alur sungguhan, bukan teks contoh.
            ->assertSee('Selamat datang', false)
            ->assertSee('menu_utama');
    }

    public function test_the_conversation_keeps_its_place_between_messages(): void
    {
        $this->send('menu');
        $this->send('4');

        $bodies = BotMessage::orderBy('id')->pluck('body')->implode("\n");

        // Alur bermenu hanya dapat ditelusuri bila state-nya tersimpan; balasan
        // kedua membuktikan simulator tidak memulai ulang setiap kali.
        $this->assertStringContainsString('pertanyaan', mb_strtolower($bodies));
    }

    public function test_the_test_conversation_is_kept_apart_from_the_public(): void
    {
        $this->send('menu');

        $this->assertDatabaseHas('bot_contacts', [
            'external_id' => 'simulator-'.$this->admin->getKey(),
        ]);
    }

    /* --------------------------------------------------------------- reset */

    public function test_reset_clears_the_transcript_and_its_complaints(): void
    {
        $this->send('menu');

        $contact = BotContact::where('external_id', 'simulator-'.$this->admin->getKey())->firstOrFail();

        Complaint::create([
            'bot_contact_id' => $contact->getKey(),
            'complaint_category_id' => \App\Models\ComplaintCategory::where('slug', 'irigasi')->value('id'),
            'ticket' => 'SIMULASI1', 'channel' => 'telegram', 'status' => 'baru',
            'description' => 'Pengaduan dari latihan.',
        ]);

        $this->actingAs($this->admin)->delete(route('admin.bot.simulator.reset'))->assertRedirect();

        // Latihan tidak boleh tertinggal di angka laporan pengaduan.
        $this->assertDatabaseMissing('complaints', ['ticket' => 'SIMULASI1']);
        $this->assertDatabaseMissing('bot_contacts', ['id' => $contact->getKey()]);
        $this->assertSame(0, BotMessage::count());
    }

    /* -------------------------------------------------------------- guards */

    public function test_an_empty_message_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bot.simulator.send'), ['channel' => 'telegram', 'text' => ''])
            ->assertSessionHasErrors('text');
    }

    public function test_somebody_without_chatbot_rights_cannot_use_it(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('admin.bot.simulator.index'))->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('admin.bot.simulator.send'), ['channel' => 'telegram', 'text' => 'menu'])
            ->assertForbidden();
    }
}
