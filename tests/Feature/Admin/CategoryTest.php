<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
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

    public function test_each_module_has_its_own_taxonomy_screen(): void
    {
        foreach (['admin.news.category.index', 'admin.services.category.index',
            'admin.documents.category.index', 'admin.faq.category.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    public function test_a_category_is_created_under_the_type_of_its_route(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.services.category.store'), ['name' => 'Perizinan'])
            ->assertRedirect(route('admin.services.category.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Perizinan',
            'slug' => 'perizinan',
            'type' => Category::TYPE_SERVICE,
        ]);
    }

    public function test_the_same_slug_may_exist_in_two_different_taxonomies(): void
    {
        Category::create(['type' => Category::TYPE_NEWS, 'name' => 'Umum']);

        $this->actingAs($this->admin)
            ->post(route('admin.faq.category.store'), ['name' => 'Umum'])
            ->assertRedirect();

        $this->assertSame(2, Category::where('slug', 'umum')->count());
    }

    public function test_a_category_cannot_be_reached_through_another_modules_url(): void
    {
        $newsCategory = Category::create(['type' => Category::TYPE_NEWS, 'name' => 'Infrastruktur']);

        // Same id, wrong taxonomy: the URL must not expose it.
        $this->actingAs($this->admin)
            ->get(route('admin.services.category.edit', $newsCategory))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.services.category.destroy', $newsCategory))
            ->assertNotFound();

        $this->assertDatabaseHas('categories', ['id' => $newsCategory->id, 'deleted_at' => null]);
    }

    public function test_a_category_cannot_become_its_own_parent(): void
    {
        $category = Category::create(['type' => Category::TYPE_NEWS, 'name' => 'Induk']);

        $this->actingAs($this->admin)
            ->put(route('admin.news.category.update', $category), [
                'name' => 'Induk',
                'parent_id' => $category->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_a_viewer_cannot_create_categories(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)
            ->post(route('admin.news.category.store'), ['name' => 'Tidak boleh'])
            ->assertForbidden();
    }
}
