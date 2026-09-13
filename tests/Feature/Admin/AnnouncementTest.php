<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
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

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'badge' => 'Info Terbaru',
            'title' => 'Layanan Perizinan Daring Dibuka',
            'body' => 'Pengajuan izin kini sepenuhnya daring.',
            'code' => 'IZIN2026',
            'button_text' => 'Lihat Caranya',
            'link' => '/layanan',
            'ticker_text' => 'Perizinan daring telah dibuka.',
            'is_active' => true,
        ]);
    }

    /* -------------------------------------------------------------- admin */

    public function test_the_announcement_screens_render(): void
    {
        $announcement = $this->announcement();

        foreach ([
            route('admin.announcements.index'),
            route('admin.announcements.create'),
            route('admin.announcements.edit', $announcement),
            route('admin.announcements.preview'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_the_datatable_carries_the_columns_the_listing_shows(): void
    {
        $this->announcement();

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.announcements.data').'?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data.0');

        foreach (['title', 'ticker', 'code', 'status', 'sort_order', 'start_date', 'end_date', 'actions'] as $column) {
            $this->assertArrayHasKey($column, $row);
        }
    }

    public function test_an_announcement_can_be_created(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'badge' => 'Agenda',
            'title' => 'Konsultasi Publik Tata Ruang',
            'body' => 'Masyarakat diundang menyampaikan masukan.',
            'code' => 'TATARUANG',
            'button_text' => 'Daftar',
            'link' => '/berita',
            'ticker_text' => 'Konsultasi publik dibuka.',
            'is_active' => 1,
        ])->assertRedirect(route('admin.announcements.index'));

        $announcement = Announcement::firstWhere('title', 'Konsultasi Publik Tata Ruang');

        $this->assertSame('Agenda', $announcement->badge);
        $this->assertSame('TATARUANG', $announcement->code);
        $this->assertTrue($announcement->is_active);
    }

    public function test_a_new_announcement_goes_to_the_end_of_the_order(): void
    {
        $this->announcement(['sort_order' => 40]);

        $this->actingAs($this->admin)->post(route('admin.announcements.store'), ['title' => 'Berikutnya']);

        $this->assertSame(50, Announcement::firstWhere('title', 'Berikutnya')->sort_order);
    }

    public function test_a_button_without_a_destination_is_not_stored(): void
    {
        // Half a call to action is either a dead control or an invisible link.
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'title' => 'Tanpa Tautan',
            'button_text' => 'Klik Saya',
        ])->assertRedirect();

        $announcement = Announcement::firstWhere('title', 'Tanpa Tautan');

        $this->assertNull($announcement->button_text);
        $this->assertNull($announcement->link);
        $this->assertFalse($announcement->hasAction());
    }

    public function test_a_scripted_link_is_refused(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:x'] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
                'title' => 'Percobaan',
                'button_text' => 'Klik',
                'link' => $bad,
            ])->assertSessionHasErrors('link');
        }

        $this->assertSame(0, Announcement::where('title', 'Percobaan')->count());
    }

    public function test_an_end_date_before_the_start_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'title' => 'Rentang Terbalik',
            'start_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_date' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('end_date');
    }

    public function test_an_announcement_can_be_toggled_and_reordered(): void
    {
        $first = $this->announcement(['title' => 'Satu', 'sort_order' => 10]);
        $second = $this->announcement(['title' => 'Dua', 'sort_order' => 20]);

        $this->actingAs($this->admin)->patch(route('admin.announcements.toggle', $first))->assertRedirect();
        $this->assertFalse($first->fresh()->is_active);

        $this->actingAs($this->admin)
            ->postJson(route('admin.announcements.reorder'), ['order' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
    }

    public function test_the_preview_shows_announcements_the_public_cannot_see(): void
    {
        $this->announcement(['title' => 'Belum Aktif', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.announcements.preview'))
            ->assertOk()
            ->assertSee('Belum Aktif')
            // Its own forced copy only: the layout must not add a second.
            ->assertSeeInOrder(['data-announcements', 'data-force'], false);

        $content = $this->actingAs($this->admin)->get(route('admin.announcements.preview'))->getContent();
        $this->assertSame(1, substr_count($content, 'data-announcement-modal'));
    }

    public function test_a_viewer_cannot_change_announcements(): void
    {
        $announcement = $this->announcement();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('announcement.view');

        $this->actingAs($viewer)->patch(route('admin.announcements.toggle', $announcement))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.announcements.store'), ['title' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.announcements.reorder'), ['order' => [$announcement->id]])->assertForbidden();
    }

    public function test_someone_without_the_module_cannot_reach_it_at_all(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('news.view');

        $this->actingAs($outsider)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('admin.announcements.data'))->assertForbidden();
    }

    /* ------------------------------------------------------------- public */

    public function test_the_strip_sits_above_the_header(): void
    {
        $this->announcement();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, '<header'),
            strpos($content, 'data-ticker'),
            'Strip pengumuman harus berada sebelum header.',
        );
    }

    public function test_only_live_announcements_reach_the_public(): void
    {
        $this->announcement(['title' => 'Tayang']);
        $this->announcement(['title' => 'Nonaktif', 'is_active' => false]);
        $this->announcement(['title' => 'Terjadwal', 'start_date' => now()->addWeek()]);
        $this->announcement(['title' => 'Kedaluwarsa', 'end_date' => now()->subDay()]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Tayang')
            ->assertDontSee('Nonaktif')
            ->assertDontSee('Terjadwal')
            ->assertDontSee('Kedaluwarsa');
    }

    public function test_nothing_renders_when_there_is_nothing_to_announce(): void
    {
        $this->get(route('public.home'))->assertOk()->assertDontSee('data-announcements', false);
    }

    public function test_the_strip_can_be_stopped_and_announces_itself(): void
    {
        $this->announcement(['title' => 'Satu']);
        $this->announcement(['title' => 'Dua']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('aria-label="Pengumuman"', false)
            // Motion that starts on its own needs a real stop (WCAG 2.2.2).
            ->assertSee('data-ticker-toggle', false)
            ->assertSee('Hentikan teks berjalan');
    }

    public function test_a_single_announcement_gets_no_pause_control(): void
    {
        // Nothing is moving, so a pause button would be a control over nothing.
        $this->announcement();

        $this->get(route('public.home'))->assertOk()->assertDontSee('data-ticker-toggle', false);
    }

    public function test_the_ticker_falls_back_to_the_title(): void
    {
        $this->announcement(['title' => 'Judul Panjang Pengumuman', 'ticker_text' => null]);

        $this->get(route('public.home'))->assertOk()->assertSee('Judul Panjang Pengumuman');
    }

    public function test_announcement_content_is_escaped(): void
    {
        $this->announcement([
            'title' => '<script>alert(1)</script>',
            'ticker_text' => '"><img src=x onerror=alert(1)>',
        ]);

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        // The payload may appear as visible text — that is what escaping does.
        // What must not appear is a tag or an attribute the browser acts on.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringNotContainsString('<img src=x', $content);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $content);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $content);
    }

    public function test_the_action_link_is_marked_so_it_can_close_the_modal(): void
    {
        // Following the call to action answers the notice; without this hook
        // the dialog stays open behind the next page and opens again on it.
        $this->announcement();

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-announcement-action', false);
    }

    public function test_the_modal_heading_is_not_a_focus_target(): void
    {
        // The global rule paints a 2px ring on anything focused, which looked
        // like a stray black line struck through the title.
        $this->announcement();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-announcement-heading', $content);
        $this->assertStringNotContainsString('data-announcement-heading tabindex', $content);
    }

    public function test_the_dismissal_signature_changes_when_an_announcement_is_edited(): void
    {
        $announcement = $this->announcement();

        $before = $this->signature();

        $this->travel(1)->minute();
        $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Judul Diperbarui',
        ])->assertRedirect();

        // Otherwise a visitor who dismissed the old notice would never be
        // shown the new one.
        $this->assertNotSame($before, $this->signature());
    }

    private function signature(): string
    {
        $content = $this->get(route('public.home'))->getContent();
        preg_match('/data-signature="([^"]+)"/', $content, $matches);

        return $matches[1] ?? '';
    }
}
