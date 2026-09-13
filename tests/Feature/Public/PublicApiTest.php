<?php

namespace Tests\Feature\Public;

use App\Models\Document;
use App\Models\Faq;
use App\Models\News;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
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

    public function test_every_endpoint_uses_the_same_envelope(): void
    {
        News::factory()->published()->create();
        Service::factory()->published()->create();

        foreach ([
            'api.public.home', 'api.public.menus', 'api.public.settings', 'api.public.carousel',
            'api.public.news.index', 'api.public.news.categories',
            'api.public.services.index', 'api.public.services.categories',
            'api.public.documents.index', 'api.public.faqs',
        ] as $route) {
            $this->getJson(route($route))
                ->assertOk()
                ->assertJsonStructure(['success', 'message', 'data', 'meta'])
                ->assertJsonPath('success', true);
        }
    }

    public function test_a_missing_resource_returns_the_error_envelope(): void
    {
        $this->getJson(route('api.public.news.show', 'tidak-ada'))
            ->assertNotFound()
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);
    }

    public function test_the_list_endpoint_omits_the_article_body(): void
    {
        News::factory()->published()->create(['content' => 'Isi lengkap yang panjang.']);

        $list = $this->getJson(route('api.public.news.index'))->assertOk();
        $this->assertArrayNotHasKey('content', $list->json('data.0'));

        $slug = News::first()->slug;
        $detail = $this->getJson(route('api.public.news.show', $slug))->assertOk();
        $this->assertSame('Isi lengkap yang panjang.', $detail->json('data.content'));
    }

    public function test_unpublished_content_is_absent_from_the_api(): void
    {
        News::factory()->create(['title' => 'Draf', 'status' => News::STATUS_DRAFT]);
        $published = News::factory()->published()->create(['title' => 'Terbit']);

        $response = $this->getJson(route('api.public.news.index'))->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Terbit', $response->json('data.0.title'));

        $this->getJson(route('api.public.news.show', News::firstWhere('title', 'Draf')->slug))
            ->assertNotFound();
    }

    public function test_a_service_detail_carries_requirements_tariffs_steps_and_total(): void
    {
        $service = Service::factory()->published()->create(['processing_time' => '5 hari kerja']);
        $service->requirements()->create(['name' => 'KTP', 'is_required' => true]);
        $service->tariffs()->createMany([
            ['name' => 'Administrasi', 'amount' => 10000, 'is_active' => true],
            ['name' => 'Pemeriksaan', 'amount' => 15000, 'is_active' => true],
        ]);
        $service->steps()->create(['name' => 'Pengajuan']);

        $this->getJson(route('api.public.services.show', $service->slug))
            ->assertOk()
            ->assertJsonPath('data.processing_time', '5 hari kerja')
            ->assertJsonPath('data.total_tariff', 25000)
            ->assertJsonCount(1, 'data.requirements')
            ->assertJsonCount(2, 'data.tariffs')
            ->assertJsonCount(1, 'data.steps');
    }

    public function test_a_document_exposes_the_controlled_download_url_not_a_storage_path(): void
    {
        $document = Document::factory()->create(['file_path' => 'documents/rahasia-nama-asli.pdf']);

        $response = $this->getJson(route('api.public.documents.show', $document->slug))->assertOk();

        $this->assertSame(route('public.documents.download', $document->slug), $response->json('data.download_url'));

        // The path on disk must never appear anywhere in the payload.
        $this->assertStringNotContainsString('rahasia-nama-asli', $response->getContent());
        $this->assertStringNotContainsString('storage/app', $response->getContent());
    }

    public function test_the_settings_endpoint_exposes_only_public_identity(): void
    {
        $response = $this->getJson(route('api.public.settings'))->assertOk();

        $data = $response->json('data');

        $this->assertArrayHasKey('site_name', $data);
        $this->assertArrayHasKey('accessibility', $data);

        // Nothing from the SEO or internal groups should leak here.
        $this->assertArrayNotHasKey('seo_keywords', $data);
        $this->assertArrayNotHasKey('canonical', $data);
    }

    public function test_the_menu_endpoint_returns_a_nested_tree(): void
    {
        $response = $this->getJson(route('api.public.menus'))->assertOk();

        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('children', $response->json('data.0'));
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->getJson(route('api.public.news.index', ['per_page' => 5000]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_the_api_refuses_write_verbs(): void
    {
        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->json($verb, route('api.public.news.index'))->assertStatus(405);
        }
    }

    public function test_a_page_can_be_fetched_by_slug(): void
    {
        Page::create([
            'title' => 'Profil', 'slug' => 'profil', 'content' => 'Isi profil.',
            'status' => Page::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        $this->getJson(route('api.public.pages.show', 'profil'))
            ->assertOk()
            ->assertJsonPath('data.title', 'Profil');
    }

    public function test_faqs_are_returned(): void
    {
        Faq::create(['question' => 'Apa?', 'answer' => 'Ini.', 'is_active' => true]);
        Faq::create(['question' => 'Tersembunyi?', 'answer' => 'Ya.', 'is_active' => false]);

        $response = $this->getJson(route('api.public.faqs'))->assertOk();

        $this->assertCount(1, $response->json('data'));
    }
}
