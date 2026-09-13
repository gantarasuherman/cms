<?php

namespace Tests\Feature\Admin;

use App\Models\AdminMenu;
use App\Models\User;
use App\Services\Menu\MenuService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(MenuSeeder::class);
    }

    public function test_the_sidebar_is_rendered_from_the_database(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Konten')
            ->assertSee('Semua Berita')
            ->assertSee('Pengguna &amp; Akses', false);
    }

    public function test_menu_items_are_hidden_when_the_user_lacks_the_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $response = $this->actingAs($viewer)->get(route('admin.dashboard'))->assertOk();

        // Viewer holds every *.view grant, so read-only entries stay visible...
        $response->assertSee('Semua Berita');

        // ...but a role limited to news alone must not see the access section.
        $newsOnly = User::factory()->create();
        $newsOnly->givePermissionTo('news.view');

        $this->actingAs($newsOnly)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Semua Berita')
            ->assertDontSee('Audit Log');
    }

    public function test_a_parent_survives_when_only_its_child_is_permitted(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('news.view');

        $tree = app(MenuService::class)->adminTree($user);

        $content = $tree->firstWhere('slug', 'content');

        $this->assertNotNull($content, 'The parent group must remain reachable.');
        $this->assertSame(['news'], $content->children->pluck('slug')->all());
        $this->assertSame(['news-all'], $content->children->first()->children->pluck('slug')->all());
    }

    public function test_an_unresolvable_route_degrades_to_a_placeholder_link(): void
    {
        $menu = AdminMenu::create([
            'title' => 'Rusak',
            'route' => 'route.that.does.not.exist',
        ]);

        $this->assertSame('#', $menu->link());
    }

    public function test_reseeding_updates_menus_instead_of_duplicating_them(): void
    {
        $before = AdminMenu::count();

        $this->seed(MenuSeeder::class);

        $this->assertSame($before, AdminMenu::count());
    }
}
