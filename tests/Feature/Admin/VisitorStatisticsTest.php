<?php

namespace Tests\Feature\Admin;

use App\Models\PageView;
use App\Models\User;
use App\Services\Analytics\VisitorStatistics;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VisitorStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';

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

        Cache::flush();
    }

    private function visit(string $url, string $agent = self::BROWSER): void
    {
        $this->withHeaders(['User-Agent' => $agent])->get($url);
    }

    /* --------------------------------------------------------- recording */

    public function test_a_public_page_view_is_recorded(): void
    {
        $this->visit(route('public.home'));

        $this->assertSame(1, PageView::count());

        $view = PageView::first();
        $this->assertSame('public.home', $view->route_name);
        $this->assertSame(Carbon::today()->toDateString(), $view->viewed_on->toDateString());
    }

    public function test_admin_and_api_requests_are_not_recorded(): void
    {
        $this->actingAs($this->admin);
        $this->visit(route('admin.dashboard'));
        $this->visit(route('admin.news.index'));
        $this->visit(route('api.public.news.index'));

        $this->assertSame(0, PageView::count());
    }

    public function test_crawlers_are_not_counted_as_visitors(): void
    {
        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'curl/8.4.0',
            'python-requests/2.31.0',
            'facebookexternalhit/1.1',
        ] as $agent) {
            $this->visit(route('public.home'), $agent);
        }

        $this->assertSame(0, PageView::count());
    }

    public function test_a_request_without_a_user_agent_is_treated_as_a_bot(): void
    {
        // Nothing legitimate browses without one; treating it as a bot keeps
        // scripted traffic out of the counts.
        $this->withHeaders(['User-Agent' => ''])->get(route('public.home'));

        $this->assertSame(0, PageView::count());
    }

    public function test_a_missing_page_is_not_counted_as_a_visit(): void
    {
        $this->visit('/berita/tidak-ada-sama-sekali');

        $this->assertSame(0, PageView::count());
    }

    /* ----------------------------------------------------------- privacy */

    public function test_no_personal_data_is_stored(): void
    {
        $this->withHeaders([
            'User-Agent' => self::BROWSER,
            'referer' => 'https://www.google.com/search?q=kata+kunci+rahasia',
        ])->get(route('public.home'));

        $view = PageView::firstOrFail();
        $row = json_encode($view->getAttributes());

        // No address, no user agent, and no referrer query string.
        $this->assertStringNotContainsString('127.0.0.1', $row);
        $this->assertStringNotContainsString('Mozilla', $row);
        $this->assertStringNotContainsString('kata+kunci+rahasia', $row);

        // Only the referring host survives.
        $this->assertSame('www.google.com', $view->referrer_host);
        $this->assertSame(64, strlen($view->visitor_hash));
    }

    public function test_the_visitor_hash_rotates_daily_so_it_cannot_follow_anyone(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $this->visit(route('public.home'));
        $today = PageView::latest('id')->first()->visitor_hash;

        Carbon::setTestNow('2026-09-13 10:00:00');
        $this->visit(route('public.home'));
        $tomorrow = PageView::latest('id')->first()->visitor_hash;

        $this->assertNotSame($today, $tomorrow, 'Hash yang sama lintas hari berarti pengunjung dapat dilacak.');

        Carbon::setTestNow();
    }

    public function test_the_same_visitor_within_one_day_counts_once(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->visit(route('public.home'));
        }

        $this->assertSame(4, PageView::count());
        $this->assertSame(1, PageView::distinct()->count('visitor_hash'));
    }

    /* -------------------------------------------------------- resilience */

    public function test_a_recording_failure_never_breaks_the_page(): void
    {
        // The table is gone; the visitor must still get their page.
        \Illuminate\Support\Facades\Schema::drop('page_views');

        $this->visit(route('public.home'));

        $this->withHeaders(['User-Agent' => self::BROWSER])
            ->get(route('public.home'))
            ->assertOk();
    }

    public function test_recording_stops_when_analytics_is_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $this->visit(route('public.home'));

        $this->assertSame(0, PageView::count());
    }

    /* -------------------------------------------------------- aggregation */

    public function test_the_daily_series_reports_quiet_days_as_zero(): void
    {
        PageView::create([
            'path' => '/', 'visitor_hash' => str_repeat('a', 64),
            'viewed_on' => Carbon::today()->subDays(3), 'viewed_at' => now()->subDays(3),
        ]);

        $daily = app(VisitorStatistics::class)->daily(7);

        $this->assertCount(7, $daily, 'Rentang harus utuh, bukan hanya hari yang ada datanya.');
        $this->assertSame(1, $daily->firstWhere('label', Carbon::today()->subDays(3)->translatedFormat('d M'))['visitors']);
        $this->assertSame(0, $daily->last()['visitors']);
    }

    public function test_the_summary_compares_against_the_previous_period(): void
    {
        foreach ([['a', 0], ['b', 0], ['c', 1]] as [$who, $daysAgo]) {
            PageView::create([
                'path' => '/', 'visitor_hash' => str_repeat($who, 64),
                'viewed_on' => Carbon::today()->subDays($daysAgo),
                'viewed_at' => now()->subDays($daysAgo),
            ]);
        }

        $summary = app(VisitorStatistics::class)->summary();

        $this->assertSame(2, $summary['visitors_today']['value']);
        $this->assertSame(1, $summary['visitors_today']['previous']);
        $this->assertSame(100.0, $summary['visitors_today']['delta']);
    }

    public function test_growth_from_zero_reports_no_percentage(): void
    {
        PageView::create([
            'path' => '/', 'visitor_hash' => str_repeat('a', 64),
            'viewed_on' => Carbon::today(), 'viewed_at' => now(),
        ]);

        // A jump from nothing has no meaningful percentage; the tile shows the
        // raw numbers instead of inventing one.
        $this->assertNull(app(VisitorStatistics::class)->summary()['visitors_today']['delta']);
    }

    public function test_top_pages_are_ranked_by_views(): void
    {
        foreach (['berita', 'berita', 'berita', 'layanan'] as $path) {
            PageView::create([
                'path' => $path, 'visitor_hash' => str_repeat('a', 64),
                'viewed_on' => Carbon::today(), 'viewed_at' => now(),
            ]);
        }

        $top = app(VisitorStatistics::class)->topPages();

        $this->assertSame('/berita', $top->first()['label']);
        $this->assertSame(3, $top->first()['views']);
    }

    /* ------------------------------------------------------------ pruning */

    public function test_records_past_the_retention_window_are_pruned(): void
    {
        PageView::create([
            'path' => '/', 'visitor_hash' => str_repeat('a', 64),
            'viewed_on' => Carbon::today()->subDays(400), 'viewed_at' => now()->subDays(400),
        ]);
        PageView::create([
            'path' => '/', 'visitor_hash' => str_repeat('b', 64),
            'viewed_on' => Carbon::today(), 'viewed_at' => now(),
        ]);

        $this->artisan('visitors:prune', ['--days' => 365])->assertSuccessful();

        $this->assertSame(1, PageView::count());
    }

    /* ---------------------------------------------------------- dashboard */

    public function test_the_dashboard_shows_the_visitor_section(): void
    {
        $this->visit(route('public.home'));

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Statistik Pengunjung')
            ->assertSee('Pengunjung hari ini')
            ->assertSee('Pengunjung 14 hari terakhir')
            ->assertSee('Lihat sebagai tabel');
    }

    public function test_the_chart_ships_a_table_view_with_every_value(): void
    {
        $this->visit(route('public.home'));
        $this->visit(route('public.news.index'));

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // The table twin carries the numbers, so no value is hover-only.
        $response->assertSee('<caption class="sr-only">Pengunjung 14 hari terakhir dalam bentuk tabel</caption>', false);
        $response->assertSee('Kunjungan', false);
    }

    public function test_the_dashboard_says_so_when_tracking_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pencatatan kunjungan dimatikan')
            ->assertDontSee('Pengunjung 14 hari terakhir');
    }
}
