<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_index_lists_news_for_permitted_users(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.news.index'))
            ->assertOk()
            ->assertSee('Semua Berita');
    }

    public function test_datatable_endpoint_returns_the_expected_envelope(): void
    {
        News::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.news.data').'?draw=1&start=0&length=10');

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->assertJsonPath('recordsTotal', 3);
    }

    public function test_a_news_item_can_be_created_with_multiple_categories(): void
    {
        Storage::fake('public');

        $categories = Category::factory()->count(2)->create(['type' => Category::TYPE_NEWS]);

        $this->actingAs($this->admin)->post(route('admin.news.store'), [
            'title' => 'Pembangunan Jembatan Selesai',
            'content' => 'Isi berita lengkap.',
            'status' => News::STATUS_PUBLISHED,
            'categories' => $categories->pluck('id')->all(),
            'tags' => ['infrastruktur', 'pembangunan'],
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertRedirect();

        $news = News::firstWhere('title', 'Pembangunan Jembatan Selesai');

        $this->assertNotNull($news);
        $this->assertSame('pembangunan-jembatan-selesai', $news->slug);
        $this->assertCount(2, $news->categories);
        $this->assertCount(2, $news->tags);
        $this->assertNotNull($news->published_at, 'Publishing must stamp published_at.');
        Storage::disk('public')->assertExists($news->featured_image);
    }

    public function test_slug_is_kept_unique(): void
    {
        News::factory()->create(['title' => 'Berita Sama', 'slug' => 'berita-sama']);

        $this->actingAs($this->admin)->post(route('admin.news.store'), [
            'title' => 'Berita Sama',
            'status' => News::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertSame(2, News::where('slug', 'like', 'berita-sama%')->count());
        $this->assertNotNull(News::firstWhere('slug', 'berita-sama-2'));
    }

    public function test_a_news_item_can_be_updated(): void
    {
        $news = News::factory()->create(['title' => 'Judul Lama']);

        $this->actingAs($this->admin)->put(route('admin.news.update', $news), [
            'title' => 'Judul Baru',
            'slug' => $news->slug,
            'status' => News::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertSame('Judul Baru', $news->fresh()->title);
    }

    public function test_a_news_item_can_be_deleted(): void
    {
        $news = News::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.news.destroy', $news))
            ->assertRedirect(route('admin.news.index'));

        $this->assertSoftDeleted($news);
    }

    public function test_a_user_without_permission_cannot_reach_news(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('admin.news.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.news.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.news.store'), [
            'title' => 'Tidak boleh',
            'status' => News::STATUS_DRAFT,
        ])->assertForbidden();
    }

    public function test_changes_are_written_to_the_audit_log(): void
    {
        $this->actingAs($this->admin)->post(route('admin.news.store'), [
            'title' => 'Berita Audit',
            'status' => News::STATUS_DRAFT,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'news',
            'action' => 'create',
            'user_id' => $this->admin->id,
        ]);
    }
}
