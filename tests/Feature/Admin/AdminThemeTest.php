<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemeTest extends TestCase
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

    public function test_the_shell_is_built_from_design_tokens(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // Components read tokens, not literal colours, so the theme can switch.
        foreach (['bg-sidebar', 'bg-card', 'text-muted-foreground', 'border-border', 'bg-primary'] as $token) {
            $response->assertSee($token, false);
        }
    }

    public function test_the_theme_is_applied_before_first_paint(): void
    {
        // Without this a dark-mode user sees a white flash on every page load.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee("classList.toggle('dark'", false)
            ->assertSee('admin-theme', false);
    }

    public function test_the_theme_toggle_announces_its_state_and_destination(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('data-theme-toggle', false)
            ->assertSee('aria-pressed', false)
            // The label says what pressing it will do, not what the state is.
            ->assertSee('Aktifkan mode gelap');
    }

    public function test_the_sidebar_shows_the_account_and_a_way_out(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($this->admin->name)
            ->assertSee($this->admin->email)
            ->assertSee(route('admin.logout'), false);
    }

    public function test_top_level_menu_groups_are_headings_not_buttons(): void
    {
        // In this layout a group label names its section; the items below stay
        // visible rather than hiding behind a disclosure.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Konten')
            ->assertSee('Semua Berita');
    }

    public function test_the_admin_panel_is_never_indexable(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }
}
