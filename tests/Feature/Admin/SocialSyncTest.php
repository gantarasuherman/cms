<?php

namespace Tests\Feature\Admin;

use App\Models\SocialPost;
use App\Models\User;
use App\Services\Social\SocialSyncService;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SocialSyncTest extends TestCase
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

        config([
            'social.sync_enabled' => true,
            'social.instagram.user_id' => '17841400000000000',
            'social.instagram.token' => 'RAHASIA-TOKEN',
            'social.youtube.api_key' => 'KUNCI-YT',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function socialPost(array $attributes = []): SocialPost
    {
        return SocialPost::create($attributes + [
            'platform' => 'instagram',
            'image' => null,
            'permalink' => 'https://www.instagram.com/p/ABC123/',
            'is_active' => true,
        ]);
    }

    private function sync(): SocialSyncService
    {
        return app(SocialSyncService::class);
    }

    /* ---------------------------------------------------------- instagram */

    public function test_figures_and_handle_are_read_from_the_platform(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['id' => '1789', 'permalink' => 'https://www.instagram.com/p/ABC123/', 'like_count' => 1284, 'comments_count' => 96, 'username' => 'dinaspupr'],
        ]])]);

        $post = $this->socialPost();

        $this->assertTrue($this->sync()->sync($post)->successful());

        $post->refresh();
        $this->assertSame(1284, $post->likes);
        $this->assertSame(96, $post->comments);
        $this->assertSame('dinaspupr', $post->account_handle);
        $this->assertSame('1789', $post->remote_id);
        $this->assertNotNull($post->synced_at);
        $this->assertSame('ok', $post->sync_status);
    }

    public function test_a_resolved_id_is_reused_instead_of_scanning_again(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '1789', 'like_count' => 12, 'comments_count' => 3])]);

        $post = $this->socialPost(['remote_id' => '1789']);
        $this->sync()->sync($post);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/1789')
            && ! str_contains($request->url(), '/media'));
    }

    public function test_permalinks_match_despite_trailing_slashes_and_query_strings(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['id' => '1', 'permalink' => 'https://www.instagram.com/p/ABC123', 'like_count' => 5],
        ]])]);

        $post = $this->socialPost(['permalink' => 'https://www.instagram.com/p/ABC123/?utm_source=web']);

        $this->assertTrue($this->sync()->sync($post)->successful());
        $this->assertSame(5, $post->fresh()->likes);
    }

    public function test_a_real_instagram_permalink_syncs_and_keeps_following_the_source(): void
    {
        $url = 'https://www.instagram.com/p/DdLkhm_k23y/?img_index=1';

        // A sequence, not two fake() calls: a second fake() is merged onto the
        // first, so the original stub would keep winning and the "source
        // changed" half of this test would never run.
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [[
                'id' => '17900000000000001',
                'permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/',
                'like_count' => 1284,
                'comments_count' => 96,
                'username' => 'dinaspupr',
                'caption' => 'Peninjauan progres pembangunan jalan.',
            ]]])
            ->push([
                'id' => '17900000000000001',
                'like_count' => 1510,
                'comments_count' => 118,
                'username' => 'dinaspupr.id',
                'caption' => 'Keterangan diperbarui.',
            ]),
        ]);

        $post = $this->socialPost(['permalink' => $url]);
        $this->assertTrue($this->sync()->sync($post)->successful());

        $post->refresh();
        $this->assertSame(1284, $post->likes);
        $this->assertSame(96, $post->comments);
        $this->assertSame('dinaspupr', $post->account_handle);
        $this->assertSame('Peninjauan progres pembangunan jalan.', $post->caption);

        // The source changes; the next run must follow it rather than keep the
        // first figures it ever saw.
        $this->sync()->sync($post);

        $post->refresh();
        $this->assertSame(1510, $post->likes);
        $this->assertSame(118, $post->comments);
        $this->assertSame('dinaspupr.id', $post->account_handle);
        $this->assertSame('Keterangan diperbarui.', $post->caption);
    }

    public function test_a_post_excluded_from_sync_keeps_the_wording_an_editor_chose(): void
    {
        Http::fake();

        $post = $this->socialPost(['sync_enabled' => false, 'caption' => 'Teks pilihan redaksi.']);
        $this->artisan('social:sync');

        $this->assertSame('Teks pilihan redaksi.', $post->fresh()->caption);
        Http::assertNothingSent();
    }

    public function test_a_post_that_is_not_on_the_account_is_reported(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

        $post = $this->socialPost();
        $result = $this->sync()->sync($post);

        $this->assertFalse($result->successful());
        $this->assertSame('failed', $post->fresh()->sync_status);
        $this->assertStringContainsString('tidak ditemukan', $post->fresh()->sync_message);
    }

    /* ------------------------------------------------------------ safety */

    public function test_a_failure_never_wipes_the_numbers_already_stored(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth token']], 400)]);

        $post = $this->socialPost(['likes' => 1284, 'comments' => 96]);
        $this->sync()->sync($post);

        // A briefly unreadable figure is better stale than blanked: zero would
        // read as "this post lost its likes".
        $post->refresh();
        $this->assertSame(1284, $post->likes);
        $this->assertSame(96, $post->comments);
        $this->assertSame('failed', $post->sync_status);
    }

    public function test_the_access_token_never_reaches_the_stored_message(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Bad token RAHASIA-TOKEN supplied'],
        ], 400)]);

        $post = $this->socialPost();
        $this->sync()->sync($post);

        $this->assertStringNotContainsString('RAHASIA-TOKEN', $post->fresh()->sync_message);
        $this->assertStringContainsString('[token]', $post->fresh()->sync_message);
    }

    public function test_a_missing_figure_is_not_written_as_zero(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['id' => '1', 'permalink' => 'https://www.instagram.com/p/ABC123/', 'like_count' => 40],
        ]])]);

        $post = $this->socialPost();
        $this->sync()->sync($post);

        $this->assertSame(40, $post->fresh()->likes);
        $this->assertNull($post->fresh()->comments, 'Komentar tidak dilaporkan, jadi harus tetap kosong.');
    }

    public function test_a_network_fault_is_caught_rather_than_thrown(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $post = $this->socialPost(['likes' => 7]);
        $result = $this->sync()->sync($post);

        $this->assertFalse($result->successful());
        $this->assertSame(7, $post->fresh()->likes);
    }

    /* ------------------------------------------------------------ youtube */

    public function test_youtube_reads_statistics_by_video_id(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['items' => [[
            'statistics' => ['likeCount' => '250', 'commentCount' => '18'],
            'snippet' => ['channelTitle' => 'Dinas PUPR', 'title' => 'Progres jalan'],
        ]]])]);

        $post = $this->socialPost(['platform' => 'youtube', 'permalink' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->assertTrue($this->sync()->sync($post)->successful());

        $post->refresh();
        $this->assertSame(250, $post->likes);
        $this->assertSame('Dinas PUPR', $post->account_handle);
        $this->assertSame('dQw4w9WgXcQ', $post->remote_id);
    }

    public function test_youtube_accepts_the_short_and_shorts_url_forms(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['items' => [[
            'statistics' => ['likeCount' => '1'], 'snippet' => ['channelTitle' => 'A'],
        ]]])]);

        foreach (['https://youtu.be/abc123XYZ', 'https://www.youtube.com/shorts/abc123XYZ'] as $url) {
            $post = $this->socialPost(['platform' => 'youtube', 'permalink' => $url]);
            $this->assertTrue($this->sync()->sync($post)->successful(), $url);
            $this->assertSame('abc123XYZ', $post->fresh()->remote_id);
        }
    }

    /* -------------------------------------------------------- unsupported */

    public function test_platforms_without_a_read_path_say_so_instead_of_guessing(): void
    {
        Http::fake();

        foreach (['x', 'tiktok'] as $platform) {
            $post = $this->socialPost(['platform' => $platform, 'likes' => 99, 'permalink' => "https://{$platform}.test/1"]);
            $result = $this->sync()->sync($post);

            $this->assertSame('unsupported', $result->status);
            $this->assertSame(99, $post->fresh()->likes, 'Angka manual harus dibiarkan.');
        }

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_platform_makes_no_request(): void
    {
        config(['social.instagram.token' => null]);
        Http::fake();

        $this->assertSame('unsupported', $this->sync()->sync($this->socialPost())->status);
        Http::assertNothingSent();
    }

    public function test_the_master_switch_stops_every_request(): void
    {
        config(['social.sync_enabled' => false]);
        Http::fake();

        $this->assertSame('unsupported', $this->sync()->sync($this->socialPost())->status);
        Http::assertNothingSent();
    }

    /* ------------------------------------------------- membuat dari tautan */

    public function test_a_post_is_created_from_nothing_but_its_link(): void
    {
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [[
                'id' => '17900000000000001',
                'permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/',
                'like_count' => 1284,
                'comments_count' => 96,
                'username' => 'dinaspupr',
                'caption' => 'Peninjauan progres pembangunan jalan.',
                'media_type' => 'IMAGE',
                'media_url' => 'https://scontent.cdninstagram.com/v/gambar.jpg',
                'timestamp' => '2026-09-10T04:20:00+0000',
            ]]]),
            'scontent.cdninstagram.com/*' => Http::response('BINER-GAMBAR', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.social-posts.fetch'), ['permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/?img_index=1'])
            ->assertRedirect();

        $post = SocialPost::firstOrFail();

        $this->assertSame('instagram', $post->platform);
        $this->assertSame('dinaspupr', $post->account_handle);
        $this->assertSame(1284, $post->likes);
        $this->assertSame(96, $post->comments);
        $this->assertSame('Peninjauan progres pembangunan jalan.', $post->caption);
        $this->assertSame('2026-09-10', $post->posted_at?->format('Y-m-d'));

        // The picture is downloaded onto our own disk, never hot-linked: a
        // hot-link would call the platform's CDN from every visitor's browser.
        $this->assertNotNull($post->image);
        Storage::disk('public')->assertExists($post->image);
        $this->assertStringStartsWith('social-posts/', $post->image);
    }

    public function test_the_platform_is_worked_out_from_the_host_not_the_string(): void
    {
        $this->assertSame('instagram', SocialPost::platformFromUrl('https://www.instagram.com/p/X/'));
        $this->assertSame('youtube', SocialPost::platformFromUrl('https://youtu.be/abc123'));
        $this->assertSame('x', SocialPost::platformFromUrl('https://x.com/a/status/1'));

        // A URL that merely contains the word is not a post on that platform.
        $this->assertNull(SocialPost::platformFromUrl('https://contoh.test/?r=instagram.com'));
    }

    public function test_an_unrecognised_link_is_refused_before_anything_is_written(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.social-posts.fetch'), ['permalink' => 'https://contoh.test/p/1'])
            ->assertSessionHasErrors('permalink');

        $this->assertSame(0, SocialPost::count());
        Http::assertNothingSent();
    }

    public function test_pasting_the_same_link_twice_opens_the_existing_post(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

        $url = 'https://www.instagram.com/p/DdLkhm_k23y/';

        $this->actingAs($this->admin)->post(route('admin.social-posts.fetch'), ['permalink' => $url]);
        $this->actingAs($this->admin)->post(route('admin.social-posts.fetch'), ['permalink' => $url])
            ->assertRedirect(route('admin.social-posts.edit', SocialPost::firstOrFail()));

        $this->assertSame(1, SocialPost::count());
    }

    public function test_a_carousel_takes_the_slide_the_link_pointed_at(): void
    {
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [[
                'id' => '1',
                'permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/',
                'media_type' => 'CAROUSEL_ALBUM',
                'children' => ['data' => [
                    ['media_url' => 'https://scontent.cdninstagram.com/satu.jpg'],
                    ['media_url' => 'https://scontent.cdninstagram.com/dua.jpg'],
                ]],
            ]]]),
            'scontent.cdninstagram.com/dua.jpg' => Http::response('SLIDE-DUA', 200, ['Content-Type' => 'image/jpeg']),
            'scontent.cdninstagram.com/*' => Http::response('SLIDE-SATU', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($this->admin)->post(route('admin.social-posts.fetch'), [
            'permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/?img_index=2',
        ]);

        $post = SocialPost::firstOrFail();
        $this->assertSame('SLIDE-DUA', Storage::disk('public')->get($post->image));
    }

    public function test_every_slide_of_a_carousel_is_stored_in_order(): void
    {
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [[
                'id' => '1',
                'permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/',
                'media_type' => 'CAROUSEL_ALBUM',
                'like_count' => 16500,
                'children' => ['data' => [
                    ['media_url' => 'https://scontent.cdninstagram.com/satu.jpg'],
                    ['media_url' => 'https://scontent.cdninstagram.com/dua.jpg'],
                    ['media_type' => 'VIDEO', 'media_url' => 'https://scontent.cdninstagram.com/tiga.mp4', 'thumbnail_url' => 'https://scontent.cdninstagram.com/tiga.jpg'],
                ]],
            ]]]),
            'scontent.cdninstagram.com/dua.jpg' => Http::response('DUA', 200, ['Content-Type' => 'image/jpeg']),
            'scontent.cdninstagram.com/tiga.jpg' => Http::response('TIGA', 200, ['Content-Type' => 'image/jpeg']),
            'scontent.cdninstagram.com/*' => Http::response('SATU', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $post = $this->socialPost(['permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/?img_index=2']);
        $this->assertTrue($this->sync()->sync($post)->successful());

        $slides = $post->refresh()->media;

        $this->assertCount(3, $slides);
        $this->assertSame(['SATU', 'DUA', 'TIGA'], $slides->map(fn ($s) => Storage::disk('public')->get($s->path))->all());

        // The video contributes its poster frame, not the file itself.
        $this->assertSame(['image', 'image', 'video'], $slides->pluck('kind')->all());

        // The cover still follows the slide the link pointed at, and reuses
        // that slide's file rather than downloading a fourth copy.
        $this->assertSame($slides[1]->path, $post->image);
        $this->assertSame('CAROUSEL_ALBUM', $post->media_type);
    }

    public function test_a_slide_removed_at_the_source_is_dropped_here_too(): void
    {
        Storage::fake('public');

        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [[
                'id' => '1',
                'permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/',
                'media_type' => 'CAROUSEL_ALBUM',
                'children' => ['data' => [
                    ['media_url' => 'https://scontent.cdninstagram.com/satu.jpg'],
                    ['media_url' => 'https://scontent.cdninstagram.com/dua.jpg'],
                ]],
            ]]])
            ->push([
                'id' => '1',
                'media_type' => 'CAROUSEL_ALBUM',
                'children' => ['data' => [
                    ['media_url' => 'https://scontent.cdninstagram.com/satu.jpg'],
                ]],
            ]),
        ]);

        Http::fake(['scontent.cdninstagram.com/*' => Http::response('GAMBAR', 200, ['Content-Type' => 'image/jpeg'])]);

        $post = $this->socialPost(['permalink' => 'https://www.instagram.com/p/DdLkhm_k23y/']);
        $this->sync()->sync($post);
        $this->assertCount(2, $post->refresh()->media);

        $dropped = $post->media[1]->path;

        $this->sync()->sync($post);

        $this->assertCount(1, $post->refresh()->media);
        Storage::disk('public')->assertMissing($dropped);
    }

    public function test_someone_elses_post_falls_back_to_official_oembed(): void
    {
        Storage::fake('public');
        config(['social.instagram.oembed_token' => '123|rahasia']);

        Http::fake([
            // Not on our account: the media list comes back without it.
            'graph.facebook.com/*/media*' => Http::response(['data' => []]),
            'graph.facebook.com/*instagram_oembed*' => Http::response([
                'author_name' => 'akunlain',
                'thumbnail_url' => 'https://scontent.cdninstagram.com/pratinjau.jpg',
            ]),
            'scontent.cdninstagram.com/*' => Http::response('PRATINJAU', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $post = $this->socialPost(['account_handle' => null, 'likes' => null]);
        $result = $this->sync()->sync($post);

        $this->assertTrue($result->successful());
        $this->assertSame('akunlain', $post->refresh()->account_handle);
        $this->assertSame('PRATINJAU', Storage::disk('public')->get($post->image));

        // oEmbed does not report engagement, and a zero would be a lie.
        $this->assertNull($post->likes);
        $this->assertStringContainsString('oEmbed', $post->sync_message);
    }

    public function test_oembed_is_not_reached_for_a_dead_token(): void
    {
        config(['social.instagram.oembed_token' => '123|rahasia']);

        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['code' => 190, 'message' => 'Session has expired'],
        ], 400)]);

        $result = $this->sync()->sync($this->socialPost());

        // A revoked token is a fault to fix, not a post to look up elsewhere.
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('tidak berlaku lagi', $result->message);
    }

    public function test_an_unchanged_picture_is_not_downloaded_again(): void
    {
        Storage::fake('public');

        // A sequence, not two fake() calls: a second fake() merges onto the
        // first, so the original stub would keep answering. Meta's URLs carry
        // an expiring signature, so the query string differs between the two
        // replies without the picture having changed.
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['id' => '1', 'like_count' => 5, 'media_url' => 'https://scontent.cdninstagram.com/v/gambar.jpg?oe=EXPIRES'])
                ->push(['id' => '1', 'like_count' => 6, 'media_url' => 'https://scontent.cdninstagram.com/v/gambar.jpg?oe=NANTI']),
            'scontent.cdninstagram.com/*' => Http::response('GAMBAR', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $post = $this->socialPost(['remote_id' => '1']);
        $this->sync()->sync($post);
        $first = $post->fresh()->image;

        $this->sync()->sync($post);

        $this->assertSame($first, $post->fresh()->image);
        $this->assertSame(6, $post->fresh()->likes);

        // One fetch of the picture across two syncs.
        Http::assertSentCount(3);
    }

    public function test_a_non_image_response_is_refused_rather_than_stored(): void
    {
        Storage::fake('public');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [[
                'id' => '1', 'permalink' => 'https://www.instagram.com/p/ABC123/',
                'media_url' => 'https://scontent.cdninstagram.com/payload',
            ]]]),
            // Whatever the URL claimed, this is not a picture.
            'scontent.cdninstagram.com/*' => Http::response('<?php echo 1;', 200, ['Content-Type' => 'text/html']),
        ]);

        $post = $this->socialPost();
        $this->sync()->sync($post);

        $this->assertNull($post->fresh()->image);
        $this->assertSame(0, count(Storage::disk('public')->allFiles()));
    }

    public function test_a_post_still_waiting_for_its_picture_is_held_back(): void
    {
        $this->socialPost(['image' => null, 'caption' => 'Menunggu gambar']);

        $this->assertSame(0, SocialPost::live()->count());

        // Unless Instagram is rendering the post itself, in which case the
        // media arrives with the embed and none of ours is needed.
        $this->assertSame(1, SocialPost::live(embedded: true)->count());
    }

    /* -------------------------------------------------------------- admin */

    public function test_an_admin_can_sync_one_post_and_all_of_them(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['id' => '1', 'permalink' => 'https://www.instagram.com/p/ABC123/', 'like_count' => 11, 'comments_count' => 2],
        ]])]);

        $post = $this->socialPost();

        $this->actingAs($this->admin)->post(route('admin.social-posts.sync', $post))->assertRedirect();
        $this->assertSame(11, $post->fresh()->likes);

        $this->actingAs($this->admin)->post(route('admin.social-posts.sync-all'))->assertRedirect();
    }

    public function test_a_viewer_cannot_trigger_a_sync(): void
    {
        Http::fake();
        $post = $this->socialPost();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('social_post.view');

        $this->actingAs($viewer)->post(route('admin.social-posts.sync', $post))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.social-posts.sync-all'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_the_index_reports_which_platforms_are_ready(): void
    {
        // Instagram and YouTube are configured in setUp; Facebook is not.
        $this->actingAs($this->admin)
            ->get(route('admin.social-posts.index'))
            ->assertOk()
            ->assertSee('Sinkronisasi Angka')
            ->assertSee('Siap')
            ->assertSee('FACEBOOK_PAGE_TOKEN')
            // Neither secret may ever be printed on a screen.
            ->assertDontSee('RAHASIA-TOKEN')
            ->assertDontSee('KUNCI-YT');
    }

    public function test_the_command_runs_and_reports(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['id' => '1', 'permalink' => 'https://www.instagram.com/p/ABC123/', 'like_count' => 3],
        ]])]);

        $this->socialPost();

        $this->artisan('social:sync')->assertSuccessful();
    }

    public function test_a_post_can_be_left_out_of_the_scheduled_sync(): void
    {
        Http::fake();
        $this->socialPost(['sync_enabled' => false, 'likes' => 5]);

        $this->artisan('social:sync')->assertSuccessful();

        Http::assertNothingSent();
    }
}
