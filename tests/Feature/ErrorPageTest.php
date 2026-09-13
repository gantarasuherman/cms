<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_missing_page_shows_the_styled_404(): void
    {
        $this->get('/halaman-yang-tidak-pernah-ada')
            ->assertNotFound()
            ->assertSee('Oops! Halaman tidak ditemukan!')
            ->assertSee('Ke Beranda')
            // Built from the same tokens as the admin shell.
            ->assertSee('bg-background', false);
    }

    public function test_the_big_number_is_decorative_and_the_heading_carries_the_message(): void
    {
        $response = $this->get('/tidak-ada')->assertNotFound();

        // A screen reader should hear the sentence, not "four zero four".
        $this->assertMatchesRegularExpression('/<p[^>]*aria-hidden="true"[^>]*>404<\/p>/', $response->getContent());
        $this->assertStringContainsString('<h1 class="font-medium">Oops! Halaman tidak ditemukan!</h1>', $response->getContent());
    }

    public function test_there_is_always_a_way_out_without_javascript(): void
    {
        // history.back() needs JS; the link beside it never does.
        $this->get('/tidak-ada')
            ->assertNotFound()
            ->assertSee('href="'.url('/').'"', false);
    }

    public function test_error_pages_are_never_indexable(): void
    {
        $this->get('/tidak-ada')
            ->assertNotFound()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_a_forbidden_action_shows_the_styled_403(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('news.view');

        $this->actingAs($viewer)
            ->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertSee('Akses ditolak!');
    }

    public function test_an_unknown_admin_url_does_not_leak_the_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        // The 404 is the plain error page, not a rendered admin shell.
        $this->actingAs($admin)
            ->get('/admin/rute-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Oops! Halaman tidak ditemukan!')
            ->assertDontSee('Panel Admin');
    }
}
