<?php

namespace Tests\Feature\Admin;

use App\Models\HomepageSection;
use App\Models\SocialPost;
use App\Models\User;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use App\Services\Settings\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SocialPostTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(HomepageSectionSeeder::class);
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        // These tests are about the card this site renders itself. Instagram's
        // own embed ships on by default and replaces it, so it is switched off
        // here; the two tests that are about the embed turn it back on.
        $this->embed(false);
    }

    private function embed(bool $on): void
    {
        app(SettingService::class)->put('appearance', ['instagram_embed' => $on ? '1' : '0'], ['instagram_embed' => 'boolean']);
    }

    private function socialPost(array $attributes = []): SocialPost
    {
        return SocialPost::create($attributes + [
            'platform' => 'instagram',
            'image' => 'social-posts/contoh.jpg',
            'caption' => 'Peninjauan progres pembangunan jalan penghubung antar desa.',
            'permalink' => 'https://www.instagram.com/p/contoh/',
            'posted_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    /* -------------------------------------------------------------- admin */

    public function test_the_screens_render(): void
    {
        $post = $this->socialPost();

        foreach ([
            route('admin.social-posts.index'),
            route('admin.social-posts.create'),
            route('admin.social-posts.edit', $post),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_the_datatable_carries_the_columns_the_listing_shows(): void
    {
        $this->socialPost();

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.social-posts.data').'?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data.0');

        foreach (['preview', 'platform', 'caption', 'status', 'sort_order', 'posted_at', 'actions'] as $column) {
            $this->assertArrayHasKey($column, $row);
        }

        $this->assertSame('Instagram', $row['platform']);
    }

    public function test_a_post_can_be_created_with_an_upload(): void
    {
        $this->actingAs($this->admin)->post(route('admin.social-posts.store'), [
            'platform' => 'facebook',
            'permalink' => 'https://www.facebook.com/contoh/posts/1',
            'caption' => 'Sosialisasi layanan perizinan daring.',
            'alt_text' => 'Suasana sosialisasi di aula kecamatan',
            'image' => UploadedFile::fake()->image('post.jpg', 1080, 1080),
            'is_active' => 1,
        ])->assertRedirect(route('admin.social-posts.index'));

        $post = SocialPost::firstWhere('platform', 'facebook');

        $this->assertSame('Suasana sosialisasi di aula kecamatan', $post->alt_text);
        Storage::disk('public')->assertExists($post->image);
    }

    public function test_an_unknown_platform_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.social-posts.store'), [
            'platform' => 'friendster',
            'permalink' => 'https://example.test/p/1',
            'image' => UploadedFile::fake()->image('post.jpg'),
        ])->assertSessionHasErrors('platform');
    }

    public function test_a_non_http_permalink_is_refused(): void
    {
        // The card is a link a visitor clicks; a javascript: scheme here would
        // be a scripted payload wearing a link's clothes.
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '/berita', ''] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.social-posts.store'), [
                'platform' => 'instagram',
                'permalink' => $bad,
                'image' => UploadedFile::fake()->image('post.jpg'),
            ])->assertSessionHasErrors('permalink');
        }

        $this->assertSame(0, SocialPost::count());
    }

    public function test_an_executable_upload_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.social-posts.store'), [
            'platform' => 'instagram',
            'permalink' => 'https://www.instagram.com/p/x/',
            'image' => UploadedFile::fake()->create('payload.php', 12, 'application/x-php'),
        ])->assertSessionHasErrors('image');
    }

    public function test_editing_without_a_new_file_keeps_the_picture(): void
    {
        $post = $this->socialPost();

        $this->actingAs($this->admin)->put(route('admin.social-posts.update', $post), [
            'platform' => 'instagram',
            'permalink' => $post->permalink,
            'caption' => 'Keterangan diperbarui.',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('social-posts/contoh.jpg', $post->fresh()->image);
        $this->assertSame('Keterangan diperbarui.', $post->fresh()->caption);
    }

    public function test_deleting_removes_the_file_too(): void
    {
        Storage::disk('public')->put('social-posts/hapus.jpg', 'isi');
        $post = $this->socialPost(['image' => 'social-posts/hapus.jpg']);

        $this->actingAs($this->admin)->delete(route('admin.social-posts.destroy', $post))->assertRedirect();

        Storage::disk('public')->assertMissing('social-posts/hapus.jpg');
    }

    public function test_posts_can_be_toggled_and_reordered(): void
    {
        $first = $this->socialPost(['sort_order' => 10, 'permalink' => 'https://www.instagram.com/p/satu/']);
        $second = $this->socialPost(['sort_order' => 20, 'permalink' => 'https://www.instagram.com/p/dua/']);

        $this->actingAs($this->admin)->patch(route('admin.social-posts.toggle', $first))->assertRedirect();
        $this->assertFalse($first->fresh()->is_active);

        $this->actingAs($this->admin)
            ->postJson(route('admin.social-posts.reorder'), ['order' => [$second->id, $first->id]])
            ->assertOk();

        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
    }

    public function test_a_viewer_cannot_change_posts(): void
    {
        $post = $this->socialPost();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('social_post.view');

        $this->actingAs($viewer)->patch(route('admin.social-posts.toggle', $post))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.social-posts.destroy', $post))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.social-posts.reorder'), ['order' => [$post->id]])->assertForbidden();
    }

    /* ------------------------------------------------------------- public */

    public function test_the_section_renders_on_the_homepage(): void
    {
        $this->socialPost();

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Ikuti Kami')
            ->assertSee('https://www.instagram.com/p/contoh/', false)
            ->assertSee('Peninjauan progres pembangunan jalan');
    }

    public function test_hidden_posts_do_not_reach_the_public(): void
    {
        $this->socialPost(['caption' => 'Tampil', 'permalink' => 'https://www.instagram.com/p/a/']);
        $this->socialPost(['caption' => 'Disembunyikan', 'permalink' => 'https://www.instagram.com/p/b/', 'is_active' => false]);

        $this->get(route('public.home'))->assertOk()->assertSee('Tampil')->assertDontSee('Disembunyikan');
    }

    public function test_the_section_disappears_when_there_is_nothing_to_show(): void
    {
        $this->get(route('public.home'))->assertOk()->assertDontSee('home-social', false);
    }

    public function test_the_admin_limit_is_honoured(): void
    {
        foreach (range(1, 6) as $i) {
            $this->socialPost(['permalink' => "https://www.instagram.com/p/{$i}/", 'caption' => "Unggahan {$i}"]);
        }

        HomepageSection::where('type', 'social_posts')->update(['settings' => ['limit' => 3]]);

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertSame(3, substr_count($content, 'instagram.com/p/'));
    }

    public function test_outgoing_links_are_safe_and_described(): void
    {
        $this->socialPost();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        // A new tab without rel=noopener hands the opener to the target page.
        $this->assertStringContainsString('rel="noopener noreferrer"', $content);
        // Leaving the site must be said, not only signalled by an icon.
        $this->assertStringContainsString('(tab baru)', $content);
    }

    public function test_every_picture_carries_alt_text(): void
    {
        $this->socialPost(['alt_text' => null, 'caption' => 'Kegiatan bersih sungai bersama warga.']);

        // Falls back to the caption: unlike a hero photograph, nothing else on
        // the card describes the picture.
        $this->get(route('public.home'))->assertOk()->assertSee('alt="Kegiatan bersih sungai bersama warga."', false);
    }

    public function test_engagement_figures_are_stored_and_shown(): void
    {
        $this->socialPost(['likes' => 1284, 'comments' => 96, 'account_handle' => 'dinaspupr']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('1.284')
            ->assertSee('dinaspupr')
            ->assertSee('suka');
    }

    public function test_large_counts_are_abbreviated_the_way_the_platforms_do(): void
    {
        $this->assertNull(SocialPost::formatCount(null));
        $this->assertSame('0', SocialPost::formatCount(0));
        $this->assertSame('999', SocialPost::formatCount(999));
        $this->assertSame('1.284', SocialPost::formatCount(1284));
        $this->assertSame('12,4 rb', SocialPost::formatCount(12400));
        $this->assertSame('1,3 jt', SocialPost::formatCount(1250000));
    }

    public function test_an_unrecorded_count_shows_nothing_rather_than_zero(): void
    {
        // Zero would be a claim that nobody liked the post; blank admits we
        // simply do not know.
        $this->socialPost(['likes' => null, 'comments' => null]);

        $content = $this->get(route('public.home'))->assertOk()->getContent();
        $start = strpos($content, 'id="home-social"');
        $section = substr($content, $start, strpos($content, '</section>', $start) - $start);

        // The whole line is absent rather than blank: Instagram prints
        // "16.523 suka" or nothing, and a lone glyph would invite a guess.
        $this->assertStringNotContainsString(' suka', $section);
        $this->assertStringNotContainsString('komentar', $section);
        $this->assertDoesNotMatchRegularExpression('/>\s*0\s*</', $section);
    }

    public function test_the_card_offers_no_control_that_does_nothing(): void
    {
        $this->socialPost(['likes' => 10]);

        $content = $this->get(route('public.home'))->assertOk()->getContent();
        $start = strpos($content, 'id="home-social"');
        $section = substr($content, $start, strpos($content, '</section>', $start) - $start);

        // A heart button that likes nothing would take focus and promise an
        // action it cannot perform. So the action row is glyphs, hidden from
        // assistive technology — and a single-picture post reaches the browser
        // with no reachable control at all: the caption toggle ships `hidden`
        // and is revealed only once the script has something to unclamp.
        $this->assertStringContainsString('class="ig-actions" aria-hidden="true"', $section);
        $this->assertStringNotContainsString('data-ig-prev', $section);

        preg_match_all('/<button\b[^>]*/', $section, $buttons);

        foreach ($buttons[0] as $button) {
            $this->assertStringContainsString('hidden', $button, 'Kendali yang belum bisa berbuat apa-apa tidak boleh terjangkau.');
        }
    }

    public function test_a_carousel_gets_controls_and_a_single_picture_does_not(): void
    {
        $post = $this->socialPost(['likes' => 10]);

        $post->media()->createMany([
            ['path' => 'social-posts/satu.jpg', 'sort_order' => 0, 'kind' => 'image'],
            ['path' => 'social-posts/dua.jpg', 'sort_order' => 1, 'kind' => 'image'],
        ]);

        $content = $this->get(route('public.home'))->assertOk()->getContent();
        $start = strpos($content, 'id="home-social"');
        $section = substr($content, $start, strpos($content, '</section>', $start) - $start);

        // Every control on the card is one the carousel script drives; there
        // is no ornamental button among them.
        preg_match_all('/<button\b[^>]*/', $section, $buttons);
        $this->assertNotEmpty($buttons[0]);

        foreach ($buttons[0] as $button) {
            $this->assertMatchesRegularExpression('/data-ig-(prev|next|dot|more)/', $button);
        }

        $this->assertStringContainsString('social-posts/dua.jpg', $section, 'Setiap slide ditampilkan, bukan sampulnya saja.');
        $this->assertStringContainsString('gambar 2 dari 2', $section, 'Posisi slide terbawa ke teks alternatif.');
    }

    public function test_hashtags_and_mentions_become_links_and_markup_does_not(): void
    {
        $this->socialPost([
            'caption' => 'Kerja bakti #jumatbersih bareng @dinaspupr <img src=x onerror=alert(1)> selesai.',
        ]);

        $content = $this->get(route('public.home'))->assertOk()->getContent();
        $start = strpos($content, 'id="home-social"');
        $section = substr($content, $start, strpos($content, '</section>', $start) - $start);

        $this->assertStringContainsString('href="https://www.instagram.com/explore/tags/jumatbersih/"', $section);
        $this->assertStringContainsString('href="https://www.instagram.com/dinaspupr/"', $section);

        // The caption is text an outside platform wrote. It reaches the page as
        // characters; the only markup in it is the markup we put there.
        $this->assertStringNotContainsString('<img src=x', $section);
        $this->assertStringContainsString('&lt;img src=x', $section);
    }

    public function test_a_handle_falls_back_to_the_site_name(): void
    {
        $post = $this->socialPost(['account_handle' => null]);

        $this->assertSame(config('app.name'), $post->handle());
        // The stored @ is decoration, not part of the name.
        $this->assertSame('dinaspupr', $this->socialPost(['account_handle' => '@dinaspupr', 'permalink' => 'https://x.test/2'])->handle());
    }

    public function test_a_malformed_handle_is_refused(): void
    {
        foreach (['dinas pupr', '<script>', 'a/b'] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.social-posts.store'), [
                'platform' => 'instagram',
                'permalink' => 'https://www.instagram.com/p/x/',
                'account_handle' => $bad,
                'image' => UploadedFile::fake()->image('post.jpg'),
            ])->assertSessionHasErrors('account_handle');
        }
    }

    public function test_a_negative_count_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.social-posts.store'), [
            'platform' => 'instagram',
            'permalink' => 'https://www.instagram.com/p/x/',
            'likes' => -5,
            'image' => UploadedFile::fake()->image('post.jpg'),
        ])->assertSessionHasErrors('likes');
    }

    public function test_no_third_party_script_or_frame_is_loaded(): void
    {
        $this->socialPost();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        // With the embed off, nothing on the page reaches a platform: the
        // pictures are served from this site's own disk and the only thing
        // pointing at instagram.com is a link somebody has to click.
        foreach (['instagram.com/embed', 'platform.instagram.com', 'connect.facebook.net', 'platform.twitter.com', '<iframe'] as $needle) {
            $this->assertStringNotContainsString($needle, $content);
        }
    }

    /* -------------------------------------------------------------- embed */

    public function test_the_official_embed_replaces_the_card_when_it_is_switched_on(): void
    {
        $this->embed(true);
        $this->socialPost(['permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/?img_index=1']);

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        // The shortcode alone: `?img_index=` and whatever the share sheet
        // appended are not part of what Instagram is asked to render.
        $this->assertStringContainsString('data-instgrm-permalink="https://www.instagram.com/p/DdLkhm_k23y/"', $content);
        $this->assertStringContainsString('https://www.instagram.com/embed.js', $content);

        // The site's own card steps aside rather than rendering underneath.
        $this->assertStringNotContainsString('article class="ig-post"', $content);

        // And the script is asked for once, however many posts there are.
        $this->assertSame(1, substr_count($content, 'instagram.com/embed.js'));
    }

    public function test_only_instagram_is_embedded_and_the_link_still_works_without_script(): void
    {
        $this->embed(true);
        $this->socialPost(['permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/']);
        $this->socialPost(['platform' => 'facebook', 'permalink' => 'https://www.facebook.com/dinas/posts/1']);

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        // Facebook has no embed here, so it keeps the site's own card.
        $this->assertStringContainsString('ig-post', $content);

        // The blockquote is the fallback, not a placeholder: with the script
        // blocked or JavaScript off, a readable link is what remains.
        $this->assertStringContainsString('Lihat unggahan', $content);
    }

    public function test_a_post_the_embed_cannot_address_falls_back_to_the_card(): void
    {
        $this->embed(true);

        // A profile link, not a post: Instagram's embed has nothing to render.
        $this->socialPost(['permalink' => 'https://www.instagram.com/dinaspupr/']);

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-instgrm-permalink', $content);
        $this->assertStringContainsString('ig-post', $content);
    }
}
