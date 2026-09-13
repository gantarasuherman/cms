<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_the_audit_screens_render(): void
    {
        $log = AuditLog::create(['action' => 'create', 'module' => 'news', 'record_id' => '1']);

        $this->actingAs($this->admin)->get(route('admin.audit-logs.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.audit-logs.show', $log))->assertOk();
    }

    public function test_an_edit_records_only_the_fields_that_changed(): void
    {
        $news = News::factory()->create(['title' => 'Judul Lama', 'excerpt' => 'Tetap sama']);

        $this->actingAs($this->admin)->put(route('admin.news.update', $news), [
            'title' => 'Judul Baru',
            'slug' => $news->slug,
            'excerpt' => 'Tetap sama',
            'status' => News::STATUS_DRAFT,
        ]);

        $log = AuditLog::where('module', 'news')->where('action', 'update')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('Judul Lama', $log->old_values['title']);
        $this->assertSame('Judul Baru', $log->new_values['title']);
        $this->assertArrayNotHasKey('excerpt', $log->new_values, 'Kolom yang tidak berubah tidak perlu dicatat.');
    }

    public function test_credentials_are_never_written_to_the_trail(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Pengguna Baru',
            'email' => 'baru@example.test',
            'password' => 'kata-sandi-rahasia',
            'password_confirmation' => 'kata-sandi-rahasia',
            'roles' => ['Viewer'],
        ])->assertRedirect();

        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('kata-sandi-rahasia', json_encode($log->new_values));
            $this->assertStringNotContainsString('kata-sandi-rahasia', json_encode($log->old_values));
        }
    }

    public function test_the_trail_records_who_did_it_and_from_where(): void
    {
        $this->actingAs($this->admin)->post(route('admin.news.store'), [
            'title' => 'Berita Tercatat',
            'status' => News::STATUS_DRAFT,
        ]);

        $log = AuditLog::where('module', 'news')->latest('id')->first();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertNotNull($log->ip);
    }

    public function test_the_datatable_can_be_filtered_by_module(): void
    {
        AuditLog::create(['action' => 'create', 'module' => 'news']);
        AuditLog::create(['action' => 'create', 'module' => 'service']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.audit-logs.data').'?draw=1&start=0&length=10&module=news')
            ->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));
    }

    public function test_the_public_cache_can_be_cleared_by_hand(): void
    {
        Cache::forever('public.news.__keys', ['public.news.abc']);
        Cache::forever('public.news.abc', ['stale']);

        $this->actingAs($this->admin)
            ->post(route('admin.audit-logs.clear-cache'))
            ->assertRedirect();

        $this->assertNull(Cache::get('public.news.abc'));
    }

    public function test_a_user_without_the_permission_cannot_read_the_trail(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('news.view');

        $this->actingAs($viewer)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('admin.audit-logs.data'))->assertForbidden();
    }

    public function test_the_trail_is_append_only_from_the_web(): void
    {
        $log = AuditLog::create(['action' => 'create', 'module' => 'news']);

        // No route exists to edit or delete an audit entry.
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit-logs'))
            ->filter(fn ($route) => array_intersect($route->methods(), ['PUT', 'PATCH', 'DELETE']) !== []);

        $this->assertCount(0, $routes);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }
}
