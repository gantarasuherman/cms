<?php

namespace Tests\Feature\Bot;

use App\Models\AuditLog;
use App\Models\Bot\BotChannel;
use App\Models\User;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotChannelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:AAH-RahasiaSekali-9f8e7d6c5b4a';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);

        config(['bot.telegram.token' => null, 'bot.whatsapp.token' => null, 'bot.whatsapp.phone_number_id' => null]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function telegram(): BotChannel
    {
        return BotChannel::where('key', 'telegram')->firstOrFail();
    }

    private function save(BotChannel $channel, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.bot.channels.update', $channel), $data + ['name' => $channel->name]);
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_screen_renders_every_channel(): void
    {
        $this->actingAs($this->admin)->get(route('admin.bot.channels.index'))
            ->assertOk()
            ->assertSee('WhatsApp')
            ->assertSee('Telegram')
            ->assertSee('Bot Token')
            ->assertSee('App Secret');
    }

    /* ------------------------------------------------------------ storing */

    public function test_a_key_entered_in_the_panel_is_used(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN, 'is_active' => 1])->assertRedirect();

        $channel = $this->telegram();

        $this->assertSame(self::TOKEN, $channel->credential('token'));
        $this->assertTrue($channel->credentialsPresent());
        $this->assertTrue($channel->isReady());
    }

    public function test_a_key_is_encrypted_at_rest(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $raw = (string) DB::table('bot_channels')->where('key', 'telegram')->value('credentials');

        // Anybody holding this token can send messages as the institution, so
        // it must not be readable in a backup or a stray SELECT.
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertStringNotContainsString('RahasiaSekali', $raw);
    }

    public function test_a_key_is_never_echoed_back_into_the_form(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $content = $this->actingAs($this->admin)->get(route('admin.bot.channels.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::TOKEN, $content);
        $this->assertStringNotContainsString('RahasiaSekali', $content);
        // Enough to tell two keys apart, never enough to use one.
        $this->assertStringContainsString('••••••••', $content);
        $this->assertStringContainsString('5b4a', $content);
    }

    public function test_a_key_never_reaches_the_audit_log(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN, 'is_active' => 1]);

        $entries = AuditLog::all()->map(fn ($log) => json_encode([$log->old_values, $log->new_values]))->implode(' ');

        // AuditLogger records the before and after of a change, which for a
        // token would be the token itself, readable by anyone with audit access.
        $this->assertStringNotContainsString(self::TOKEN, $entries);
        $this->assertStringNotContainsString('RahasiaSekali', $entries);
        $this->assertStringContainsString('kredensial_diubah', $entries);
    }

    public function test_an_empty_field_leaves_the_stored_key_alone(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN]);

        // The form never shows the value, so a blank box cannot mean "clear it".
        $this->save($this->telegram(), ['token' => '', 'is_active' => 1]);

        $this->assertSame(self::TOKEN, $this->telegram()->credential('token'));
    }

    public function test_submitting_the_mask_unchanged_does_not_overwrite_the_key(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN]);
        $mask = $this->telegram()->credentialHint('token');

        $this->save($this->telegram(), ['token' => $mask]);

        $this->assertSame(self::TOKEN, $this->telegram()->credential('token'));
    }

    public function test_a_key_can_be_taken_out_of_use(): void
    {
        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $this->actingAs($this->admin)
            ->post(route('admin.bot.channels.forget', $this->telegram()), ['field' => 'token'])
            ->assertRedirect();

        $this->assertNull($this->telegram()->credential('token'));
    }

    public function test_an_unknown_field_cannot_be_written(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bot.channels.forget', $this->telegram()), ['field' => 'app_secret'])
            ->assertNotFound();
    }

    /* -------------------------------------------------------- environment */

    public function test_the_environment_still_works_as_a_fallback(): void
    {
        config(['bot.telegram.token' => 'DARI-ENV']);

        $this->assertSame('DARI-ENV', $this->telegram()->credential('token'));
        $this->assertFalse($this->telegram()->credentialIsStored('token'));
    }

    public function test_the_panel_wins_over_the_environment(): void
    {
        config(['bot.telegram.token' => 'DARI-ENV']);
        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $this->assertSame(self::TOKEN, $this->telegram()->credential('token'));
    }

    /* ------------------------------------------------------- active state */

    public function test_activating_without_credentials_warns_rather_than_pretending(): void
    {
        $this->save($this->telegram(), ['is_active' => 1])
            ->assertSessionHas('warning');

        $channel = $this->telegram();

        // Active and mute is the trap this warns about.
        $this->assertTrue($channel->is_active);
        $this->assertFalse($channel->isReady());
        $this->assertSame(['token'], $channel->missingCredentials());
    }

    /* ------------------------------------------------------------ testing */

    public function test_a_connection_test_reports_who_the_bot_is(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'pengaduan_bot']])]);

        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $this->actingAs($this->admin)
            ->post(route('admin.bot.channels.test', $this->telegram()))
            ->assertSessionHas('success');

        $this->assertNotNull($this->telegram()->verified_at);
    }

    public function test_a_rejected_key_is_reported_without_repeating_it(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => false, 'description' => 'Unauthorized for token '.self::TOKEN,
        ], 401)]);

        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $this->actingAs($this->admin)->post(route('admin.bot.channels.test', $this->telegram()));

        $channel = $this->telegram();

        // Telegram carries the token in the URL, so an echoed error could put
        // it straight back on the screen.
        $this->assertNull($channel->verified_at);
        $this->assertStringNotContainsString(self::TOKEN, (string) $channel->last_error);
        $this->assertStringContainsString('[token]', (string) $channel->last_error);
    }

    /* ------------------------------------------------ dibaca layanan bot */

    public function test_the_bot_service_reads_the_panels_keys(): void
    {
        config(['bot.internal_token' => 'rahasia-internal', 'bot.enabled' => true]);

        $this->save($this->telegram(), ['token' => self::TOKEN, 'is_active' => 1]);

        $response = $this->withHeaders(['X-Bot-Token' => 'rahasia-internal'])
            ->getJson(route('api.bot.config'))
            ->assertOk();

        // Without this the panel would look like it worked and change nothing.
        $this->assertSame(self::TOKEN, $response->json('channels.telegram.token'));
    }

    public function test_a_disabled_channel_is_not_handed_to_the_bot_service(): void
    {
        config(['bot.internal_token' => 'rahasia-internal', 'bot.enabled' => true]);

        $this->save($this->telegram(), ['token' => self::TOKEN]);

        $response = $this->withHeaders(['X-Bot-Token' => 'rahasia-internal'])->getJson(route('api.bot.config'));

        // A channel switched off must go quiet even while the service runs.
        $this->assertNull($response->json('channels.telegram'));
    }

    public function test_the_config_endpoint_is_behind_the_same_gate(): void
    {
        config(['bot.internal_token' => 'rahasia-internal']);

        $this->getJson(route('api.bot.config'))->assertUnauthorized();
        $this->withHeaders(['X-Bot-Token' => 'salah'])->getJson(route('api.bot.config'))->assertUnauthorized();
    }

    /* ---------------------------------------------------------------- izin */

    public function test_someone_without_the_module_cannot_reach_the_keys(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $this->actingAs($outsider)->get(route('admin.bot.channels.index'))->assertForbidden();
        $this->actingAs($outsider)->put(route('admin.bot.channels.update', $this->telegram()), ['name' => 'X'])->assertForbidden();
    }

    public function test_a_viewer_can_look_but_not_change(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('chatbot.view');

        $this->actingAs($viewer)->get(route('admin.bot.channels.index'))->assertOk();
        $this->actingAs($viewer)->put(route('admin.bot.channels.update', $this->telegram()), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.bot.channels.test', $this->telegram()))->assertForbidden();
    }
}
