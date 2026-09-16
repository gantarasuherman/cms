<?php

namespace Tests\Feature\Bot;

use App\Models\AuditLog;
use App\Models\Bot\BotChannel;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Faq;
use App\Models\User;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiSettings;
use App\Services\Bot\BotEngine;
use App\Services\Bot\Messages\IncomingMessage;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'gsk_RahasiaKunciGroq_9f8e7d6c';

    private BotChannel $channel;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);
        $this->seed(BotFlowSeeder::class);

        $this->channel = BotChannel::where('whatsapp', '=', 'whatsapp')->first()
            ?? BotChannel::where('key', 'whatsapp')->firstOrFail();
        $this->channel->update(['is_active' => true]);
    }

    private function enableAi(array $overrides = []): void
    {
        app(AiSettings::class)->put($overrides + [
            'enabled' => true,
            'provider' => 'groq',
            'api_key' => self::KEY,
            'feature_intent' => true,
            'feature_answers' => true,
        ]);
    }

    private function reply(string $text): string
    {
        $messages = app(BotEngine::class)->handle($this->channel, new IncomingMessage(
            externalId: 'ai'.(++$this->counter), from: '628120000042', text: $text,
        ));

        return implode("\n", array_map(fn ($m) => $m->body, $messages));
    }

    private function fakeModel(string $content): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);
    }

    /* -------------------------------------------------------- tanpa AI */

    public function test_everything_works_exactly_as_before_when_ai_is_off(): void
    {
        Http::fake();

        $this->reply('halo');
        $this->assertStringContainsString('tidak dikenali', $this->reply('jalan depan rumah saya rusak'));

        // Off means off: nothing is sent anywhere.
        Http::assertNothingSent();
    }

    public function test_the_assistant_reports_itself_unavailable_without_a_key(): void
    {
        app(AiSettings::class)->put(['enabled' => true, 'provider' => 'groq', 'api_key' => null]);
        config(['ai.api_key' => null]);

        $this->assertTrue(app(AiSettings::class)->enabled());
        // A base URL and model come from the provider defaults, so it is
        // "configured" — but a hosted provider without a key answers nothing.
        $this->assertNull(app(AiAssistant::class)->intent('halo', [['value' => '1', 'label' => 'Pengaduan']]));
    }

    /* ------------------------------------------------------ memahami menu */

    public function test_a_sentence_is_read_as_the_menu_option_it_meant(): void
    {
        $this->enableAi();
        $this->fakeModel('1');

        $this->reply('halo');
        $reply = $this->reply('jalan depan rumah saya rusak parah sudah lama');

        // Being told "pilihan tidak dikenali" for saying so plainly is what
        // makes a menu feel like a form.
        $this->assertStringContainsString('Jenis pengaduan', $reply);
    }

    public function test_a_guess_outside_the_menu_changes_nothing(): void
    {
        $this->enableAi();
        // The main menu has four options; "9" is not one of them.
        $this->fakeModel('9');

        $this->reply('halo');

        $this->assertStringContainsString('tidak dikenali', $this->reply('sesuatu yang tidak jelas maksudnya'));
    }

    public function test_an_unsure_model_refuses_rather_than_guesses(): void
    {
        $this->enableAi();
        $this->fakeModel('TIDAK');

        $this->reply('halo');

        // Routing somebody to the wrong branch on a guess is worse than showing
        // them the menu again.
        $this->assertStringContainsString('tidak dikenali', $this->reply('entahlah bagaimana ya kira kira'));
    }

    public function test_a_model_that_fails_leaves_the_menu_working(): void
    {
        $this->enableAi();
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429)]);

        $this->reply('halo');

        $this->assertStringContainsString('tidak dikenali', $this->reply('jalan rusak di depan rumah saya'));
    }

    public function test_a_plain_number_never_reaches_the_model(): void
    {
        $this->enableAi();
        Http::fake();

        $this->reply('halo');
        $this->assertStringContainsString('Jenis pengaduan', $this->reply('1'));

        // The deterministic path must not cost a network round trip.
        Http::assertNothingSent();
    }

    /* --------------------------------------------------------- menjawab */

    private function seedFaq(): void
    {
        Faq::create([
            'question' => 'Berapa lama proses izin mendirikan bangunan?',
            'answer' => 'Proses izin mendirikan bangunan memakan waktu lima hari kerja sejak berkas lengkap.',
            'is_active' => true, 'sort_order' => 10,
        ]);
    }

    public function test_an_answer_is_written_from_the_faq(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Prosesnya memakan waktu lima hari kerja sejak berkas Anda lengkap.');

        $this->reply('halo');
        $this->reply('4');
        $reply = $this->reply('Berapa lama proses izin mendirikan bangunan?');

        $this->assertStringContainsString('lima hari kerja sejak berkas Anda lengkap', $reply);
        $this->assertSame(0, Complaint::count());
    }

    public function test_a_model_saying_it_does_not_know_falls_back_without_asking_again(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('TIDAK TAHU');

        $this->reply('halo');
        $this->reply('4');
        $reply = $this->reply('Berapa lama proses izin mendirikan bangunan?');

        // Better than a confident wrong answer from a government service. And
        // the question is not asked again: it is already remembered.
        $this->assertStringContainsString('lima hari kerja sejak berkas lengkap', $reply);
        $this->assertStringNotContainsString('Silakan tulis pertanyaan', $reply);
    }

    public function test_a_question_the_site_cannot_answer_reaches_a_person(): void
    {
        $this->enableAi();
        // An empty body: the model answered nothing usable.
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'TIDAK TAHU']]]])]);

        $this->reply('halo');
        $this->reply('4');
        $reply = $this->reply('Apakah tersedia bantuan perbaikan rumah tidak layak huni?');

        $this->assertStringContainsString('teruskan kepada petugas', mb_strtolower($reply));

        // Bidangnya ditanyakan lebih dulu, supaya pertanyaan sampai kepada
        // petugas yang mengurusnya, bukan hanya pemegang kategori "Pertanyaan".
        $position = ComplaintCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->search('lainnya');

        $this->reply((string) ($position + 1));

        $this->assertSame(1, Complaint::count());
        $this->assertSame('Apakah tersedia bantuan perbaikan rumah tidak layak huni?', Complaint::first()->description);
    }

    public function test_with_assistance_off_the_question_is_asked_the_plain_way(): void
    {
        Http::fake();

        $this->reply('halo');
        $reply = $this->reply('4');

        // Nothing has been collected yet, so there is a question to ask.
        $this->assertStringContainsString('Silakan tulis pertanyaan', $reply);
        Http::assertNothingSent();
    }

    public function test_the_model_is_given_the_retrieved_text_and_told_to_stay_in_it(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Lima hari kerja.');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('Berapa lama proses izin mendirikan bangunan?');

        Http::assertSent(function ($request) {
            $system = $request->data()['messages'][0]['content'];

            return str_contains($system, 'HANYA berdasarkan sumber')
                && str_contains($system, 'TIDAK TAHU')
                && str_contains($system, 'lima hari kerja');
        });
    }

    /* -------------------------------------------------- percakapan lanjutan */

    public function test_a_follow_up_is_understood_as_a_follow_up(): void
    {
        $this->seedFaq();
        $this->enableAi();
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'Lima hari kerja sejak berkas lengkap.']]]])
            ->push(['choices' => [['message' => ['content' => 'Tidak dipungut biaya.']]]]),
        ]);

        $this->reply('halo');
        $this->reply('4');
        $first = $this->reply('Berapa lama proses izin mendirikan bangunan?');
        $second = $this->reply('Berapa biayanya?');

        $this->assertStringContainsString('Lima hari kerja', $first);
        // The node holds its turn, so the second question is not a stranger.
        $this->assertStringContainsString('Tidak dipungut biaya', $second);

        Http::assertSent(function ($request) {
            $roles = array_column($request->data()['messages'], 'role');

            // The earlier exchange travels with the follow-up; without it
            // "berapa biayanya?" is about nothing at all.
            return in_array('assistant', $roles, true)
                && str_contains(json_encode($request->data()), 'Berapa lama proses izin');
        });
    }

    public function test_saying_done_closes_the_conversation(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Lima hari kerja.');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('Berapa lama prosesnya?');

        $this->assertStringContainsString('terima kasih', mb_strtolower($this->reply('selesai')));
    }

    public function test_a_question_that_merely_contains_a_closing_word_is_still_a_question(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Tidak dipungut biaya.');

        $this->reply('halo');
        $this->reply('4');

        // "tidak ada biaya?" is a question, not a goodbye.
        $this->assertStringContainsString('Tidak dipungut biaya', $this->reply('tidak ada biaya tambahan?'));
    }

    public function test_the_conversation_ends_at_its_turn_limit(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Jawaban singkat.');

        $this->reply('halo');
        $this->reply('4');

        $node = \App\Models\Bot\BotFlow::firstOrFail()->nodes->firstWhere('key', 'tanya_ai');
        $limit = (int) $node->setting('max_turns', 8);

        $last = '';
        for ($i = 0; $i < $limit; $i++) {
            $last = $this->reply('Pertanyaan nomor '.($i + 1).' tentang layanan?');
        }

        // Keeps costs and an endless chat in check; the answer still arrives.
        $this->assertStringContainsString('Jawaban singkat', $last);
        $this->assertStringNotContainsString('Masih ada yang ingin ditanyakan', $last);
    }

    public function test_the_model_can_tell_one_kind_of_entry_from_another(): void
    {
        $this->seed(\Database\Seeders\DemoContentSeeder::class);
        $this->enableAi();
        $this->fakeModel('Berita terbaru: …');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('ada berita apa saja hari ini?');

        Http::assertSent(function ($request) {
            $system = $request->data()['messages'][0]['content'];

            // The defect this guards: every entry arrived as an unlabelled
            // line, so asked for news the model had no way to know which rows
            // were news and answered from whatever was nearest.
            return str_contains($system, '## Berita')
                && str_contains($system, '## Tanya Jawab')
                && preg_match('/\[\d+\] \(Berita · \d{2} \w{3} \d{4}\)/u', $system) === 1;
        });
    }

    public function test_the_model_is_told_what_day_it_is(): void
    {
        $this->seed(\Database\Seeders\DemoContentSeeder::class);
        $this->enableAi();
        $this->fakeModel('…');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('ada berita apa saja hari ini?');

        Http::assertSent(function ($request) {
            // "hari ini" is unanswerable without an anchor.
            return str_contains($request->data()['messages'][0]['content'], now()->translatedFormat('d F Y'));
        });
    }

    public function test_a_request_for_a_list_is_allowed_to_be_a_list(): void
    {
        $this->seed(\Database\Seeders\DemoContentSeeder::class);
        $this->enableAi();
        $this->fakeModel('1. …');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('ada berita apa saja?');

        Http::assertSent(function ($request) {
            $system = $request->data()['messages'][0]['content'];

            // Five news items cannot fit in "maksimal empat kalimat".
            return str_contains($system, 'daftar bernomor singkat');
        });
    }

    public function test_one_long_source_cannot_crowd_the_others_out(): void
    {
        $this->seed(\Database\Seeders\DemoContentSeeder::class);

        // A FAQ big enough to have filled the whole prompt before.
        foreach (range(1, 40) as $i) {
            Faq::create([
                'question' => "Pertanyaan umum nomor {$i}?",
                'answer' => "Jawaban nomor {$i}.",
                'is_active' => true, 'sort_order' => $i,
            ]);
        }

        $this->enableAi();
        $this->fakeModel('…');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('ada berita apa saja?');

        Http::assertSent(function ($request) {
            // Taking the first forty across all sources let one long FAQ push
            // the news out of the prompt entirely.
            return str_contains($request->data()['messages'][0]['content'], '## Berita');
        });
    }

    public function test_the_answer_stays_inside_the_sources_the_node_allows(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Lima hari kerja.');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('Berapa lama proses izin mendirikan bangunan?');

        Http::assertSent(function ($request) {
            $system = $request->data()['messages'][0]['content'];

            return str_contains($system, 'HANYA berdasarkan sumber')
                && str_contains($system, 'TIDAK TAHU')
                && str_contains($system, 'jangan menebak angka, biaya')
                && str_contains($system, 'lima hari kerja');
        });
    }

    /* ------------------------------------------------------ apa yang dikirim */

    public function test_only_the_text_of_a_message_ever_leaves(): void
    {
        $this->seedFaq();
        $this->enableAi();
        $this->fakeModel('Lima hari kerja.');

        $this->reply('halo');
        $this->reply('4');
        $this->reply('Berapa lama proses izin mendirikan bangunan?');

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            // Phone numbers, coordinates and file paths are never part of a
            // prompt — the screen that enables this says so, and it must be true.
            return ! str_contains($body, '628120000042') && ! str_contains($body, 'bot/');
        });
    }

    /* ------------------------------------------------------------ setelan */

    public function test_the_key_is_encrypted_and_never_shown_or_audited(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->put(route('admin.bot.ai.update'), [
            'enabled' => 1, 'provider' => 'groq', 'api_key' => self::KEY, 'feature_intent' => 1,
        ])->assertRedirect();

        $raw = (string) DB::table('settings')->where('group', 'ai')->where('key', 'api_key')->value('value');
        $this->assertStringNotContainsString(self::KEY, $raw);
        $this->assertStringNotContainsString('RahasiaKunciGroq', $raw);

        $screen = $this->actingAs($admin)->get(route('admin.bot.ai.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::KEY, $screen);
        $this->assertStringNotContainsString('RahasiaKunciGroq', $screen);
        // Enough to recognise the key, never enough to use it.
        $this->assertStringContainsString('••••••••'.substr(self::KEY, -4), $screen);

        $audit = AuditLog::all()->map(fn ($l) => json_encode([$l->old_values, $l->new_values]))->implode(' ');
        $this->assertStringNotContainsString(self::KEY, $audit);
        $this->assertStringContainsString('kunci_diubah', $audit);
    }

    public function test_an_empty_key_field_leaves_the_stored_key_alone(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->enableAi();

        $this->actingAs($admin)->put(route('admin.bot.ai.update'), [
            'enabled' => 1, 'provider' => 'groq', 'api_key' => '',
        ]);

        $this->assertSame(self::KEY, app(AiSettings::class)->apiKey());
    }

    public function test_the_address_is_never_asked_for_and_comes_from_the_provider(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $screen = $this->actingAs($admin)->get(route('admin.bot.ai.edit'))->assertOk()->getContent();

        // An API address is an exact string a person has no way to check; one
        // wrong character produces a bot that silently stops helping.
        $this->assertStringNotContainsString('name="base_url"', $screen);

        app(AiSettings::class)->put(['provider' => 'openai']);
        $this->assertSame('https://api.openai.com/v1', app(AiSettings::class)->baseUrl());

        app(AiSettings::class)->put(['provider' => 'groq']);
        $this->assertSame('https://api.groq.com/openai/v1', app(AiSettings::class)->baseUrl());
    }

    public function test_the_model_is_chosen_from_a_list_not_typed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->get(route('admin.bot.ai.edit'))
            ->assertOk()
            ->assertSee('Llama 3.3 70B — paling mampu')
            ->assertSee('<select id="ai-model"', false);

        $this->actingAs($admin)->put(route('admin.bot.ai.update'), [
            'provider' => 'groq', 'model' => 'model-karangan-saya',
        ])->assertSessionHasErrors('model');
    }

    public function test_switching_provider_never_leaves_a_model_that_cannot_work(): void
    {
        app(AiSettings::class)->put(['provider' => 'groq', 'model' => 'llama-3.1-8b-instant']);
        $this->assertSame('llama-3.1-8b-instant', app(AiSettings::class)->model());

        // The old name would be sent to OpenAI and refused on every request
        // until somebody noticed.
        app(AiSettings::class)->put(['provider' => 'openai']);
        $this->assertArrayHasKey(app(AiSettings::class)->model(), config('ai.providers.openai.models'));
    }

    public function test_a_model_on_this_server_needs_no_key(): void
    {
        app(AiSettings::class)->put(['enabled' => true, 'provider' => 'ollama', 'api_key' => null]);
        config(['ai.api_key' => null]);

        // There is nobody to authenticate to.
        $this->assertFalse(app(AiSettings::class)->needsKey());
        $this->assertTrue(app(AiAssistant::class)->available());
    }

    public function test_a_hosted_provider_without_a_key_is_not_available(): void
    {
        app(AiSettings::class)->put(['enabled' => true, 'provider' => 'groq', 'api_key' => null]);
        config(['ai.api_key' => null]);

        $this->assertFalse(app(AiAssistant::class)->available());
    }

    public function test_a_key_without_the_switch_is_said_out_loud(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        app(AiSettings::class)->put(['enabled' => false, 'provider' => 'groq', 'api_key' => self::KEY]);

        // The easy mistake: the screen looks configured, the bot quietly falls
        // back to keyword search, and the answers wander.
        $this->actingAs($admin)->get(route('admin.bot.ai.edit'))
            ->assertOk()
            ->assertSee('masih dimatikan');

        app(AiSettings::class)->put(['enabled' => true]);

        $this->actingAs($admin)->get(route('admin.bot.ai.edit'))
            ->assertOk()
            ->assertDontSee('masih dimatikan');
    }

    public function test_an_unknown_provider_is_refused(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->put(route('admin.bot.ai.update'), ['provider' => 'model-rahasia-saya'])
            ->assertSessionHasErrors('provider');
    }

    public function test_a_viewer_cannot_change_the_ai_settings(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('chatbot.view');

        $this->actingAs($viewer)->get(route('admin.bot.ai.edit'))->assertOk();
        $this->actingAs($viewer)->put(route('admin.bot.ai.update'), ['provider' => 'groq'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.bot.ai.test'))->assertForbidden();
    }
}
