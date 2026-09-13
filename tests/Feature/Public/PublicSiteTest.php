<?php

namespace Tests\Feature\Public;

use App\Models\Category;
use App\Models\Document;
use App\Models\Faq;
use App\Models\News;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(HomepageSectionSeeder::class);
    }

    public function test_the_homepage_renders_sections_from_the_database(): void
    {
        News::factory()->published()->create(['title' => 'Berita Utama Kita', 'is_featured' => true]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Berita Utama Kita')
            ->assertSee('Lompat ke konten utama');
    }

    public function test_the_navigation_comes_from_the_database(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('Layanan')
            ->assertSee('Dokumen');
    }

    public function test_unpublished_content_is_invisible_to_the_public(): void
    {
        $draft = News::factory()->create(['title' => 'Draf Rahasia', 'status' => News::STATUS_DRAFT]);
        $scheduled = News::factory()->create([
            'title' => 'Terbit Besok',
            'status' => News::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('public.news.index'))
            ->assertOk()
            ->assertDontSee('Draf Rahasia')
            ->assertDontSee('Terbit Besok');

        // A 404, not a 403: a 403 would confirm the slug exists.
        $this->get(route('public.news.show', $draft->slug))->assertNotFound();
        $this->get(route('public.news.show', $scheduled->slug))->assertNotFound();
    }

    public function test_reading_an_article_increases_its_view_count(): void
    {
        $news = News::factory()->published()->create();

        $this->get(route('public.news.show', $news->slug))->assertOk();

        $this->assertSame(1, $news->fresh()->views);
    }

    public function test_news_can_be_filtered_by_category(): void
    {
        $category = Category::factory()->create(['type' => Category::TYPE_NEWS, 'slug' => 'infrastruktur']);

        $matching = News::factory()->published()->create(['title' => 'Berita Jalan']);
        $matching->categories()->attach($category);

        News::factory()->published()->create(['title' => 'Berita Lainnya']);

        $response = $this->get(route('public.news.index', ['kategori' => 'infrastruktur']))
            ->assertOk()
            ->assertSee('Berita Jalan');

        // The result set itself is what the filter governs. The sidebar's
        // "Terpopuler" widget is deliberately site-wide — a magazine rail that
        // narrowed with every filter would stop being a way out of the slice —
        // so the page as a whole may still mention other articles.
        $this->assertSame(1, $response->viewData('news')->total());
        $this->assertSame(
            ['Berita Jalan'],
            $response->viewData('news')->pluck('title')->all(),
        );
    }

    public function test_a_service_page_shows_requirements_tariffs_and_steps(): void
    {
        $service = Service::factory()->published()->create([
            'name' => 'Izin Contoh',
            'processing_time' => '7 hari kerja',
        ]);

        $service->requirements()->create(['name' => 'Fotokopi KTP', 'is_required' => true]);
        $service->tariffs()->createMany([
            ['name' => 'Administrasi', 'amount' => 10000, 'is_active' => true],
            ['name' => 'Pemeriksaan', 'amount' => 15000, 'is_active' => true],
        ]);
        $service->steps()->create(['name' => 'Pengajuan', 'sort_order' => 10]);

        $this->get(route('public.services.show', $service->slug))
            ->assertOk()
            ->assertSee('7 hari kerja')
            ->assertSee('Fotokopi KTP')
            ->assertSee('Wajib')
            ->assertSee('Pengajuan')
            ->assertSee('Rp25.000');
    }

    public function test_the_faq_page_renders_an_accordion(): void
    {
        Faq::create(['question' => 'Apakah gratis?', 'answer' => 'Ya, tidak dipungut biaya.', 'is_active' => true]);

        $this->get(route('public.faq.index'))
            ->assertOk()
            ->assertSee('Apakah gratis?')
            ->assertSee('aria-expanded', false);
    }

    public function test_a_cms_page_is_reachable_by_slug(): void
    {
        Page::create([
            'title' => 'Profil Kami',
            'slug' => 'profil',
            'content' => 'Isi halaman profil.',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get(route('public.pages.show', 'profil'))
            ->assertOk()
            ->assertSee('Profil Kami')
            ->assertSee('Isi halaman profil.');
    }

    public function test_search_covers_news_services_and_documents(): void
    {
        News::factory()->published()->create(['title' => 'Jembatan Baru']);
        Service::factory()->published()->create(['name' => 'Izin Jembatan']);
        Document::factory()->create(['title' => 'Laporan Jembatan']);

        $this->get(route('public.search', ['q' => 'Jembatan']))
            ->assertOk()
            ->assertSee('Jembatan Baru')
            ->assertSee('Izin Jembatan')
            ->assertSee('Laporan Jembatan');
    }

    public function test_a_search_term_that_is_too_short_is_rejected(): void
    {
        $this->get(route('public.search', ['q' => 'a']))
            ->assertSessionHasErrors('q');
    }

    public function test_the_public_surface_exposes_no_writing_routes(): void
    {
        $writable = collect(app('router')->getRoutes())
            ->filter(fn ($route) => ! str_starts_with($route->uri(), 'admin'))
            ->filter(fn ($route) => ! str_starts_with($route->uri(), '_'))
            ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []);

        // The internal bot API does write, and is not public: only the bot
        // service holds the shared secret. It is excluded by *proving* the
        // guard is on it, so a route added later without that guard still
        // fails this test rather than quietly joining the exemption.
        $guarded = $writable->filter(fn ($route) => in_array('bot.token', $route->gatherMiddleware(), true));

        foreach ($guarded as $route) {
            $this->assertStringStartsWith('api/bot/', $route->uri(), 'Gerbang bot hanya untuk rute bot internal.');
        }

        $exposed = $writable
            ->reject(fn ($route) => in_array('bot.token', $route->gatherMiddleware(), true))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values();

        $this->assertSame([], $exposed->all(), 'Rute publik harus hanya-baca.');
        $this->assertGreaterThan(0, $guarded->count(), 'Rute bot internal harus ada dan bergerbang token.');
    }

    public function test_the_sitemap_lists_only_published_content(): void
    {
        $published = News::factory()->published()->create(['slug' => 'sudah-terbit']);
        $draft = News::factory()->create(['slug' => 'masih-draf']);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('sudah-terbit')
            ->assertDontSee('masih-draf');
    }

    public function test_robots_blocks_crawlers_when_the_site_is_set_to_noindex(): void
    {
        $this->get(route('robots'))->assertOk()->assertSee('Disallow: /admin');

        app(\App\Services\Settings\SettingService::class)->put('seo', ['robots' => 'noindex, nofollow']);

        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('Disallow: /')
            ->assertDontSee('Sitemap:');
    }

    public function test_the_article_hero_sits_above_the_headline_in_the_text_column(): void
    {
        $news = News::factory()->published()->create([
            'title' => 'Berita Bergambar',
            'featured_image' => 'news/contoh.jpg',
        ]);

        $content = $this->get(route('public.news.show', $news->slug))->assertOk()->getContent();

        $article = str($content)->after('<article')->before('</article>')->value();

        $image = strpos($article, '<img');
        $headline = strpos($article, '<h1');

        $this->assertNotFalse($image, 'Gambar utama harus dirender.');
        $this->assertLessThan($headline, $image, 'Gambar mendahului judul, seperti tata letak acuannya.');

        // The article shares the header's container, so its left and right
        // edges line up with the navbar rather than sitting inset.
        $this->assertStringContainsString('mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8', $content);
        $this->assertStringContainsString('w-full rounded-lg object-cover', $article);

        // Decorative: the headline beside it already names the article.
        $this->assertMatchesRegularExpression('/<img[^>]*alt=""/', $article);
    }

    public function test_an_article_without_an_image_still_renders(): void
    {
        $news = News::factory()->published()->create(['featured_image' => null]);

        $this->get(route('public.news.show', $news->slug))
            ->assertOk()
            ->assertSee($news->title);
    }

    public function test_the_homepage_offers_a_working_search(): void
    {
        $response = $this->get(route('public.home'))->assertOk();

        // The pill posts to the real search route with the fields it validates.
        $response->assertSee('action="'.route('public.search').'"', false)
            ->assertSee('name="q"', false)
            ->assertSee('name="jenis"', false);
    }

    public function test_search_can_be_narrowed_to_one_kind_of_content(): void
    {
        News::factory()->published()->create(['title' => 'Jembatan Berita']);
        Service::factory()->published()->create(['name' => 'Jembatan Layanan']);

        $this->get(route('public.search', ['q' => 'Jembatan', 'jenis' => 'berita']))
            ->assertOk()
            ->assertSee('Jembatan Berita')
            ->assertDontSee('Jembatan Layanan');
    }

    public function test_an_unknown_content_type_is_rejected(): void
    {
        $this->get(route('public.search', ['q' => 'apa', 'jenis' => 'tidak-ada']))
            ->assertSessionHasErrors('jenis');
    }

    public function test_the_site_logo_comes_from_settings(): void
    {
        // Nothing uploaded yet: the header falls back rather than breaking.
        $this->get(route('public.home'))->assertOk()->assertDontSee('/storage/settings/');

        app(\App\Services\Settings\SettingService::class)
            ->put('general', ['logo' => 'settings/logo.png'], ['logo' => 'image']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('/storage/settings/logo.png', false);
    }

    public function test_a_draft_page_is_not_reachable(): void
    {
        Page::create(['title' => 'Rahasia', 'slug' => 'rahasia', 'status' => Page::STATUS_DRAFT]);

        $this->get(route('public.pages.show', 'rahasia'))->assertNotFound();
    }
}
