<?php

namespace Tests\Feature\Admin;

use App\Models\AdminMenu;
use App\Models\Page;
use App\Models\PublicMenu;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_both_menu_trees_have_their_own_screens(): void
    {
        $this->actingAs($this->admin)->get(route('admin.menus.admin.index'))->assertOk()->assertSee('Menu Admin');
        $this->actingAs($this->admin)->get(route('admin.menus.public.index'))->assertOk()->assertSee('Menu Publik');
    }

    public function test_a_public_menu_item_can_be_created_and_appears_on_the_site(): void
    {
        $this->actingAs($this->admin)->post(route('admin.menus.public.store'), [
            'title' => 'Peta Situs',
            'url' => '/halaman/peta',
            'target' => '_self',
            'is_active' => 1,
            'sort_order' => 99,
        ])->assertRedirect(route('admin.menus.public.index'));

        $this->assertDatabaseHas('public_menus', ['title' => 'Peta Situs', 'slug' => 'peta-situs']);

        // The navigation is read from the database, so it shows up immediately.
        $this->get(route('public.home'))->assertOk()->assertSee('Peta Situs');
    }

    public function test_each_menu_url_resolves_only_within_its_own_tree(): void
    {
        // The seeded admin tree is larger than the public one, so ids past the
        // end of public_menus exist in admin_menus alone.
        $adminOnly = AdminMenu::where('id', '>', PublicMenu::max('id'))->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.menus.public.edit', $adminOnly->id))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.menus.public.destroy', $adminOnly->id))
            ->assertNotFound();

        $this->assertDatabaseHas('admin_menus', ['id' => $adminOnly->id]);
    }

    public function test_colliding_ids_resolve_to_the_tree_named_by_the_url(): void
    {
        // Both trees start at id 1, so the URL prefix is what disambiguates.
        $publicOne = PublicMenu::orderBy('id')->firstOrFail();
        $adminOne = AdminMenu::orderBy('id')->firstOrFail();

        $this->assertSame($publicOne->id, $adminOne->id, 'Prasyarat: id bertabrakan di kedua tabel.');
        $this->assertNotSame($publicOne->title, $adminOne->title);

        // The edit form is populated from the public row, not the admin one.
        $this->actingAs($this->admin)
            ->get(route('admin.menus.public.edit', $publicOne->id))
            ->assertOk()
            ->assertSee('value="'.$publicOne->title.'"', false)
            ->assertDontSee('value="'.$adminOne->title.'"', false);
    }

    public function test_a_menu_cannot_become_its_own_parent(): void
    {
        $menu = PublicMenu::firstWhere('slug', 'berita');

        $this->actingAs($this->admin)->put(route('admin.menus.public.update', $menu), [
            'title' => $menu->title,
            'parent_id' => $menu->id,
            'target' => '_self',
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_an_admin_menu_permission_must_exist_in_the_catalogue(): void
    {
        $this->actingAs($this->admin)->post(route('admin.menus.admin.store'), [
            'title' => 'Menu Palsu',
            'permission' => 'modul.tidak.ada',
            'target' => '_self',
        ])->assertSessionHasErrors('permission');
    }

    public function test_a_menu_url_cannot_carry_a_script_scheme(): void
    {
        $this->actingAs($this->admin)->post(route('admin.menus.public.store'), [
            'title' => 'Jahat',
            'url' => 'javascript:alert(1)',
            'target' => '_self',
        ])->assertSessionHasErrors('url');
    }

    public function test_deleting_a_parent_removes_its_children(): void
    {
        $parent = PublicMenu::create(['title' => 'Induk', 'target' => '_self']);
        $child = PublicMenu::create(['title' => 'Anak', 'parent_id' => $parent->id, 'target' => '_self']);

        $this->actingAs($this->admin)
            ->delete(route('admin.menus.public.destroy', $parent))
            ->assertRedirect();

        $this->assertDatabaseMissing('public_menus', ['id' => $child->id]);
    }

    public function test_menus_can_be_reordered_and_renested(): void
    {
        $first = PublicMenu::create(['title' => 'Satu', 'target' => '_self', 'sort_order' => 10]);
        $second = PublicMenu::create(['title' => 'Dua', 'target' => '_self', 'sort_order' => 20]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.menus.public.reorder'), [
                'order' => [
                    ['id' => $second->id, 'parent_id' => null],
                    ['id' => $first->id, 'parent_id' => $second->id],
                ],
            ])
            ->assertOk();

        $this->assertSame($second->id, $first->fresh()->parent_id);
        $this->assertTrue($second->fresh()->sort_order < $first->fresh()->sort_order);
    }

    public function test_tags_can_be_managed(): void
    {
        $this->actingAs($this->admin)->get(route('admin.news.tag.index'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.news.tag.store'), ['name' => 'Infrastruktur'])
            ->assertRedirect();

        $tag = Tag::firstWhere('slug', 'infrastruktur');
        $this->assertNotNull($tag);

        $this->actingAs($this->admin)->get(route('admin.news.tag.edit', $tag))->assertOk();

        $this->actingAs($this->admin)
            ->delete(route('admin.news.tag.destroy', $tag))
            ->assertRedirect();

        $this->assertSame(0, Tag::count());
    }

    public function test_pages_can_be_managed_and_published(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pages.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.pages.create'))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.pages.store'), [
            'title' => 'Profil Organisasi',
            'content' => 'Isi profil.',
            'status' => Page::STATUS_PUBLISHED,
        ])->assertRedirect();

        $page = Page::firstWhere('slug', 'profil-organisasi');

        $this->assertNotNull($page);
        $this->assertNotNull($page->published_at);

        $this->get(route('public.pages.show', $page->slug))->assertOk()->assertSee('Isi profil.');
    }

    public function test_a_viewer_cannot_change_menus(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->post(route('admin.menus.public.store'), [
            'title' => 'Tidak boleh', 'target' => '_self',
        ])->assertForbidden();
    }
}
