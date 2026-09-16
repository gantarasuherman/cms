<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotRecipient;
use App\Models\ComplaintCategory;
use App\Models\User;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotRecipientTest extends TestCase
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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function irrigation(): ComplaintCategory
    {
        return ComplaintCategory::where('slug', 'irigasi')->firstOrFail();
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_screens_render(): void
    {
        $recipient = BotRecipient::create([
            'name' => 'Mantri Irigasi', 'channel' => 'telegram', 'destination' => '628120000001',
        ]);

        $this->actingAs($this->admin)->get(route('admin.bot.recipients.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.bot.recipients.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.bot.recipients.edit', $recipient))->assertOk();
    }

    public function test_the_list_warns_about_categories_nobody_covers(): void
    {
        $this->actingAs($this->admin)->get(route('admin.bot.recipients.index'))
            ->assertOk()
            ->assertSee('Belum ada petugas untuk');
    }

    /* ------------------------------------------------------------- writing */

    public function test_a_recipient_is_created_with_the_categories_ticked(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bot.recipients.store'), [
            'name' => 'Mantri Irigasi Sudimampir',
            'channel' => 'telegram',
            'destination' => '628120000002',
            'is_active' => 1,
            'categories' => [$this->irrigation()->id],
        ])->assertRedirect(route('admin.bot.recipients.index'));

        $recipient = BotRecipient::where('name', 'Mantri Irigasi Sudimampir')->firstOrFail();

        $this->assertTrue($recipient->is_active);
        $this->assertSame(['irigasi'], $recipient->categories->pluck('slug')->all());
    }

    public function test_unticking_a_category_stops_the_notifications(): void
    {
        $recipient = BotRecipient::create([
            'name' => 'Petugas Jalan', 'channel' => 'whatsapp', 'destination' => '628120000003',
        ]);
        $recipient->categories()->sync([$this->irrigation()->id]);

        $this->actingAs($this->admin)->put(route('admin.bot.recipients.update', $recipient), [
            'name' => 'Petugas Jalan',
            'channel' => 'whatsapp',
            'destination' => '628120000003',
            'is_active' => 1,
            // Tanpa kunci categories sama sekali — seperti formulir yang
            // seluruh kotaknya dilepas centangnya.
        ])->assertRedirect(route('admin.bot.recipients.index'));

        $this->assertCount(0, $recipient->fresh()->categories);
    }

    /* -------------------------------------------------------------- guards */

    public function test_the_destination_must_be_digits(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bot.recipients.store'), [
            'name' => 'Salah Format',
            'channel' => 'whatsapp',
            // Nomor bergaya lokal dengan tanda baca: Cloud API menolaknya, dan
            // kegagalannya baru terlihat saat pengaduan pertama tidak sampai.
            'destination' => '0812-3453-9882',
        ])->assertSessionHasErrors('destination');

        $this->assertDatabaseMissing('bot_recipients', ['name' => 'Salah Format']);
    }

    public function test_an_unknown_channel_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bot.recipients.store'), [
            'name' => 'Kanal Asing',
            'channel' => 'sms',
            'destination' => '628120000004',
        ])->assertSessionHasErrors('channel');
    }

    public function test_somebody_without_chatbot_rights_cannot_manage_recipients(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('admin.bot.recipients.index'))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.bot.recipients.store'), [
            'name' => 'Penyusup', 'channel' => 'telegram', 'destination' => '628120000005',
        ])->assertForbidden();
    }
}
