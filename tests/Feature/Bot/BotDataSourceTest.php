<?php

namespace Tests\Feature\Bot;

use App\Models\Bot\BotDataSource;
use App\Models\User;
use Database\Seeders\BotFlowSeeder;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotDataSourceTest extends TestCase
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
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_screens_render(): void
    {
        $source = BotDataSource::firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.bot.data-sources.index'))
            ->assertOk()
            ->assertSee('Berita')
            // Which flows would notice if this were removed.
            ->assertSee('dipakai 1 node');

        $this->actingAs($this->admin)->get(route('admin.bot.data-sources.edit', $source))
            ->assertOk()
            ->assertSee('data-preview-list', false)
            ->assertSee('{index}');
    }

    /* -------------------------------------------------------------- saving */

    public function test_a_source_can_be_added(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bot.data-sources.store'), [
            'name' => 'Pertanyaan Umum',
            'source' => 'faqs',
            'limit' => 6,
            'list_template' => '{index}. {title}',
            'detail_template' => '*{title}*\n\n{excerpt}',
            'is_active' => 1,
        ])->assertRedirect(route('admin.bot.data-sources.index'));

        $source = BotDataSource::where('name', 'Pertanyaan Umum')->firstOrFail();

        $this->assertSame('faqs', $source->source);
        $this->assertTrue($source->is_active);
        $this->assertStringContainsString('pertanyaan-umum', $source->slug);
    }

    public function test_a_source_outside_the_vocabulary_is_refused(): void
    {
        // A free-text model name would be a way to read the users table over
        // WhatsApp.
        foreach (['users', 'settings', 'App\\Models\\User'] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.bot.data-sources.store'), [
                'name' => 'Coba', 'source' => $bad, 'limit' => 5,
            ])->assertSessionHasErrors('source');
        }

        // Nothing was written by any of those attempts.
        $this->assertSame(BotDataSource::whereIn('source', array_keys(\App\Support\BotNodes::dataSources()))->count(), BotDataSource::count());
    }

    public function test_an_unreasonable_limit_is_refused(): void
    {
        foreach ([0, 50, -1] as $limit) {
            $this->actingAs($this->admin)->post(route('admin.bot.data-sources.store'), [
                'name' => 'Coba', 'source' => 'news', 'limit' => $limit,
            ])->assertSessionHasErrors('limit');
        }
    }

    public function test_a_source_still_used_by_a_flow_is_not_deleted(): void
    {
        $source = BotDataSource::where('slug', 'berita')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.bot.data-sources.destroy', $source))
            ->assertSessionHas('warning');

        // Deleting it would leave that node answering "sumber informasi belum
        // disiapkan" to whoever reached it.
        $this->assertNotNull(BotDataSource::find($source->id));
    }

    public function test_an_unused_source_can_be_deleted(): void
    {
        $source = BotDataSource::create([
            'name' => 'Tidak Dipakai', 'slug' => 'tidak-dipakai', 'source' => 'faqs', 'limit' => 5, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.bot.data-sources.destroy', $source))
            ->assertRedirect();

        $this->assertNull(BotDataSource::find($source->id));
    }

    /* ------------------------------------------------------------ preview */

    public function test_the_preview_renders_real_content(): void
    {
        $this->seed(DemoContentSeeder::class);

        $response = $this->actingAs($this->admin)->postJson(route('admin.bot.data-sources.preview'), [
            'source' => 'news',
            'limit' => 3,
            'list_template' => '{index}. {title}',
            'detail_template' => "*{title}*\n{date}\n\n{excerpt}\n\n{url}",
        ])->assertOk();

        $this->assertFalse($response->json('empty'));
        $this->assertMatchesRegularExpression('/^1\. /m', $response->json('list'));
        $this->assertStringContainsString('Balas nomornya', $response->json('list'));
        $this->assertStringContainsString('/berita/', $response->json('detail'));
    }

    public function test_the_preview_and_the_bot_produce_the_same_words(): void
    {
        $this->seed(DemoContentSeeder::class);

        $preview = $this->actingAs($this->admin)->postJson(route('admin.bot.data-sources.preview'), [
            'name' => 'Berita', 'source' => 'news', 'limit' => 5,
            'list_template' => '{index}. {title} ({date})',
            'detail_template' => "*{title}*\n{date}\n\n{excerpt}\n\nSelengkapnya: {url}",
        ])->json('list');

        $channel = \App\Models\Bot\BotChannel::where('key', 'whatsapp')->firstOrFail();
        $channel->update(['is_active' => true]);

        $engine = app(\App\Services\Bot\BotEngine::class);
        $say = function (string $text) use ($engine, $channel) {
            static $i = 0;
            $reply = $engine->handle($channel, new \App\Services\Bot\Messages\IncomingMessage(
                externalId: 'pv'.(++$i), from: '628120000777', text: $text,
            ));

            return implode("\n", array_map(fn ($r) => $r->body, $reply));
        };

        $say('halo');
        $say('3');
        $actual = $say('1');

        // A preview built from a second rendering path is a preview that can
        // quietly disagree with what is sent.
        $this->assertSame($preview, $actual);
    }

    public function test_every_declared_source_lists_its_items_with_a_name(): void
    {
        $this->seed(DemoContentSeeder::class);

        // The bug this guards: services are named with `name`, not `title`, so
        // the list came out as bare numbers with nothing beside them. Only
        // "news" was covered before, which is why it went unnoticed.
        foreach (array_keys(\App\Support\BotNodes::dataSources()) as $source) {
            $response = $this->actingAs($this->admin)->postJson(route('admin.bot.data-sources.preview'), [
                'name' => 'Uji', 'source' => $source, 'limit' => 5, 'list_template' => '{index}. {title}',
            ])->assertOk();

            if ($response->json('empty')) {
                continue;
            }

            foreach (explode("\n", $response->json('list')) as $line) {
                if (preg_match('/^(\d+)\.\s*(.*)$/', $line, $matches)) {
                    $this->assertNotSame('', trim($matches[2]),
                        "Sumber \"{$source}\" menampilkan nomor {$matches[1]} tanpa nama.");
                }
            }
        }
    }

    public function test_pages_are_readable_too(): void
    {
        \App\Models\Page::create([
            'title' => 'Profil Dinas', 'slug' => 'profil-dinas',
            'excerpt' => 'Sekilas tentang dinas.', 'content' => 'Isi halaman.',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.bot.data-sources.preview'), [
            'name' => 'Halaman', 'source' => 'pages', 'limit' => 5, 'list_template' => '{index}. {title}',
        ])->assertOk();

        $this->assertFalse($response->json('empty'));
        $this->assertStringContainsString('Profil Dinas', $response->json('list'));
        $this->assertStringContainsString('/halaman/profil-dinas', $response->json('detail'));
    }

    public function test_an_empty_source_says_so_rather_than_showing_a_blank_bubble(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.bot.data-sources.preview'), [
            'source' => 'documents', 'limit' => 5,
        ])->assertOk();

        $this->assertTrue($response->json('empty'));
        $this->assertStringContainsString('Belum ada isi', $response->json('list'));
    }

    public function test_the_preview_refuses_a_source_outside_the_vocabulary(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.bot.data-sources.preview'), ['source' => 'users', 'limit' => 5])
            ->assertStatus(422);
    }

    public function test_the_preview_only_reads_published_content(): void
    {
        $this->seed(DemoContentSeeder::class);

        \App\Models\News::query()->update(['status' => 'draft']);

        // The bot borrows the site's own visibility rules rather than
        // re-implementing them, so a draft can never be read out.
        $this->assertTrue($this->actingAs($this->admin)->postJson(route('admin.bot.data-sources.preview'), [
            'source' => 'news', 'limit' => 5,
        ])->json('empty'));
    }

    /* ---------------------------------------------------------------- izin */

    public function test_someone_without_the_module_cannot_reach_it(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $this->actingAs($outsider)->get(route('admin.bot.data-sources.index'))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('admin.bot.data-sources.preview'), ['source' => 'news', 'limit' => 5])->assertForbidden();
    }

    public function test_a_viewer_cannot_change_a_source(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('chatbot.view');
        $source = BotDataSource::firstOrFail();

        $this->actingAs($viewer)->get(route('admin.bot.data-sources.index'))->assertOk();
        $this->actingAs($viewer)->put(route('admin.bot.data-sources.update', $source), [
            'name' => 'Diubah', 'source' => 'news', 'limit' => 5,
        ])->assertForbidden();
    }

    /* ------------------------------------------------- terhubung ke editor */

    public function test_a_new_source_becomes_selectable_in_the_flow_editor(): void
    {
        BotDataSource::create([
            'name' => 'Pengumuman Kantor', 'slug' => 'pengumuman-kantor',
            'source' => 'pages', 'limit' => 5, 'is_active' => true,
        ]);

        $flow = \App\Models\Bot\BotFlow::firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.bot.flows.edit', $flow))
            ->assertOk()
            ->assertSee('pengumuman-kantor');
    }

    public function test_an_inactive_source_is_not_offered_to_the_editor(): void
    {
        BotDataSource::create([
            'name' => 'Arsip Lama', 'slug' => 'arsip-lama',
            'source' => 'pages', 'limit' => 5, 'is_active' => false,
        ]);

        $flow = \App\Models\Bot\BotFlow::firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.bot.flows.edit', $flow))
            ->assertOk()
            ->assertDontSee('arsip-lama');
    }
}
