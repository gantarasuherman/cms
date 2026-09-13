<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Document;
use App\Models\News;
use App\Models\User;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The security review from the specification, written as tests so it can be
 * re-run rather than signed off once.
 */
class SecurityTest extends TestCase
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

    /* ------------------------------------------------------- authentication */

    public function test_every_admin_route_requires_authentication(): void
    {
        $unguarded = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'admin') || str_contains($route->uri(), 'login')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true)) {
                $unguarded[] = $route->uri();
            }
        }

        $this->assertSame([], $unguarded, 'Setiap rute admin harus berada di belakang auth.');
    }

    public function test_session_is_regenerated_on_login_to_prevent_fixation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('kata-sandi-rahasia')]);

        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'kata-sandi-rahasia',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    /* ---------------------------------------------------------------- CSRF */

    public function test_state_changing_requests_require_a_csrf_token(): void
    {
        // The middleware is disabled inside the test harness, so the check is
        // that it is actually registered on the stack these routes run through.
        $route = app('router')->getRoutes()->getByName('admin.news.store');

        $this->assertContains('web', $route->gatherMiddleware());

        $webGroup = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];

        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $webGroup);
    }

    /* ----------------------------------------------------------------- XSS */

    public function test_content_is_escaped_when_rendered_to_visitors(): void
    {
        $payload = '<script>alert("xss")</script>';

        $news = News::factory()->published()->create([
            'title' => "Judul {$payload}",
            'content' => "Isi {$payload}",
            'excerpt' => "Ringkasan {$payload}",
        ]);

        $response = $this->get(route('public.news.show', $news->slug))->assertOk();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;', $response->getContent());
    }

    public function test_a_social_link_cannot_carry_a_javascript_url(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:msgbox(1)'] as $url) {
            $this->actingAs($this->admin)
                ->post(route('admin.settings.social.store'), ['platform' => 'Uji', 'url' => $url])
                ->assertSessionHasErrors('url');
        }
    }

    /* --------------------------------------------------------- SQL injection */

    public function test_search_terms_are_bound_not_interpolated(): void
    {
        News::factory()->published()->create(['title' => 'Berita Asli']);

        // If the term were interpolated, this would drop the table.
        $this->get(route('public.search', ['q' => "'; DROP TABLE news; --"]))->assertOk();

        $this->assertDatabaseHas('news', ['title' => 'Berita Asli']);
    }

    public function test_a_datatable_order_parameter_cannot_inject_sql(): void
    {
        News::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.news.data').'?draw=1&start=0&length=10'
                .'&columns[0][data]=title&columns[0][name]=title&columns[0][orderable]=true'
                .'&order[0][column]=0&order[0][dir]='.urlencode('asc; DROP TABLE news; --'))
            ->assertOk();

        $this->assertSame(2, News::count());
    }

    /* -------------------------------------------------------- mass assignment */

    public function test_a_user_cannot_promote_themselves_through_extra_form_fields(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $this->actingAs($editor)->post(route('admin.news.store'), [
            'title' => 'Berita',
            'status' => News::STATUS_DRAFT,
            // Not in the form request, and not fillable.
            'views' => 999999,
            'author_id' => $this->admin->id,
        ])->assertRedirect();

        $news = News::firstWhere('title', 'Berita');

        $this->assertSame(0, $news->views);
        $this->assertSame($editor->id, $news->author_id, 'Penulis ditentukan server, bukan dari formulir.');
    }

    /* ------------------------------------------------------------------ IDOR */

    public function test_ids_in_urls_do_not_grant_access_across_modules(): void
    {
        $newsCategory = Category::create(['type' => Category::TYPE_NEWS, 'name' => 'Berita Kategori']);

        $this->actingAs($this->admin)
            ->get(route('admin.documents.category.edit', $newsCategory))
            ->assertNotFound();
    }

    public function test_a_lower_privileged_user_cannot_reach_another_modules_data(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        // Operator has no user.view or role.view grant.
        $this->actingAs($operator)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    /* ---------------------------------------------------------- file upload */

    public function test_executable_uploads_are_refused(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        foreach ([
            ['shell.php', 'application/x-php'],
            ['shell.phtml', 'application/x-php'],
            ['shell.sh', 'application/x-sh'],
            ['shell.exe', 'application/octet-stream'],
        ] as [$name, $mime]) {
            $this->actingAs($this->admin)->post(route('admin.documents.store'), [
                'title' => 'Uji '.$name,
                'file' => UploadedFile::fake()->create($name, 20, $mime),
            ])->assertSessionHasErrors('file');
        }

        $this->assertSame(0, Document::count());
    }

    public function test_a_double_extension_upload_does_not_slip_through(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Ganda',
            'file' => UploadedFile::fake()->create('laporan.pdf.php', 20, 'application/x-php'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    /* -------------------------------------------------------- path traversal */

    public function test_upload_filenames_never_reach_the_filesystem(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('../../../etc/passwd.png'),
        ])->assertRedirect();

        $media = \App\Models\Media::first();

        $this->assertStringNotContainsString('..', $media->path);
        $this->assertStringNotContainsString('etc', $media->path);
    }

    public function test_the_download_route_takes_a_slug_not_a_path(): void
    {
        foreach (['../.env', '..%2F.env', '....//....//.env'] as $attempt) {
            $this->get('/dokumen/'.$attempt.'/unduh')->assertNotFound();
        }
    }

    public function test_the_private_disk_is_not_served_over_http(): void
    {
        // 'serve' => false on the local disk, so no storage route exists at all.
        $storageRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'storage/'));

        $this->assertCount(0, $storageRoutes, 'Disk privat tidak boleh punya rute HTTP.');
    }

    /* --------------------------------------------------------- rate limiting */

    public function test_public_endpoints_that_cost_the_server_are_throttled(): void
    {
        foreach ([
            'public.search' => 'throttle:search',
            'public.documents.download' => 'throttle:downloads',
            'api.public.news.index' => 'throttle:api',
        ] as $name => $expected) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertContains($expected, $route->middleware(), "Rute {$name} harus dibatasi laju.");
        }
    }

    /* ------------------------------------------------- permission bypass */

    public function test_the_ui_is_not_the_security_boundary(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        // The sidebar hides these, but posting directly must fail too.
        foreach ([
            ['post', route('admin.news.store'), ['title' => 'X', 'status' => 'draft']],
            ['post', route('admin.faq.store'), ['question' => 'X', 'answer' => 'Y']],
            ['post', route('admin.roles.store'), ['name' => 'Peran Baru']],
            ['put', route('admin.settings.general.update'), ['site_name' => 'X']],
        ] as [$verb, $url, $payload]) {
            $this->actingAs($viewer)->{$verb}($url, $payload)->assertForbidden();
        }
    }

    public function test_an_inactive_account_loses_access_immediately(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('news.view');

        $this->actingAs($user)->get(route('admin.news.index'))->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())->get(route('admin.news.index'))->assertRedirect(route('admin.login'));
    }

    /* ------------------------------------------------- public API exposure */

    public function test_the_api_never_exposes_internal_fields(): void
    {
        News::factory()->published()->create();

        $body = $this->getJson(route('api.public.news.index'))->assertOk()->getContent();

        foreach (['deleted_at', 'seo_keywords', 'author_id', 'password', 'remember_token'] as $field) {
            $this->assertStringNotContainsString($field, $body, "Field internal {$field} bocor ke API publik.");
        }
    }

    public function test_admin_pages_are_not_indexable(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }
}
