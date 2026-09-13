<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\IconSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarFlyoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IconSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_a_group_with_children_carries_a_flyout_panel(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('sidebar-node', false)
            ->assertSee('sidebar-subpanel sidebar-flyout', false)
            // The panel is headed by the group's own name, so a floating list of
            // children still says what it belongs to.
            ->assertSee('sidebar-flyout-title', false);
    }

    public function test_a_leaf_carries_its_label_for_the_collapsed_rail(): void
    {
        // With the labels visually hidden, a leaf still needs to say what it is.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('sidebar-flyout--label', false);
    }

    public function test_labels_stay_in_the_accessibility_tree_when_the_rail_narrows(): void
    {
        $content = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Collapsing hides labels with clip-path, never display:none — otherwise
        // a screen-reader user would lose the navigation entirely.
        $this->assertStringContainsString('sidebar-label', $content);
        $this->assertStringContainsString('Semua Berita', $content);
    }

    public function test_flyout_children_remain_real_links(): void
    {
        // The flyout is a view of the same tree, not a separate copy, so every
        // destination is a plain href that works without JavaScript.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.news.index'), false)
            ->assertSee(route('admin.news.category.index'), false);
    }

    public function test_group_buttons_declare_the_panel_they_control(): void
    {
        $content = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-menu-toggle\s+aria-controls="menu-panel-\d+"/', $content);
        $this->assertMatchesRegularExpression('/aria-expanded="(true|false)"/', $content);
    }
}
