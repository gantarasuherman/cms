<?php

namespace Tests\Feature\Public;

use App\Services\Settings\SettingService;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityTest extends TestCase
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

    public function test_the_accessibility_panel_is_present_for_visitors(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Buka pengaturan aksesibilitas')
            ->assertSee('Ukuran Teks')
            ->assertSee('Penglihatan Warna')
            ->assertSee('Baca Halaman');
    }

    public function test_every_colour_vision_mode_is_offered(): void
    {
        $response = $this->get(route('public.home'))->assertOk();

        foreach (['Normal', 'Protanopia', 'Deuteranopia', 'Tritanopia', 'Skala abu-abu'] as $mode) {
            $response->assertSee($mode);
        }

        // The filters themselves must ship with the page, not be fetched.
        foreach (['a11y-protanopia', 'a11y-deuteranopia', 'a11y-tritanopia', 'a11y-grayscale'] as $filter) {
            $response->assertSee($filter, false);
        }
    }

    public function test_turning_off_a_feature_removes_only_that_control(): void
    {
        app(SettingService::class)->put('accessibility', [
            'text_to_speech_enabled' => false,
        ], ['text_to_speech_enabled' => 'boolean']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('Baca halaman')
            ->assertSee('Ukuran Teks')
            ->assertSee('Penglihatan Warna');
    }

    public function test_turning_the_widget_off_hides_the_whole_panel(): void
    {
        app(SettingService::class)->put('accessibility', [
            'show_widget' => false,
        ], ['show_widget' => 'boolean']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('Buka pengaturan aksesibilitas');
    }

    public function test_disabling_accessibility_entirely_removes_the_panel(): void
    {
        app(SettingService::class)->put('accessibility', [
            'accessibility_enabled' => false,
        ], ['accessibility_enabled' => 'boolean']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('Buka pengaturan aksesibilitas');
    }

    public function test_the_widget_position_is_configurable(): void
    {
        app(SettingService::class)->put('accessibility', ['widget_position' => 'bottom-left']);

        $this->get(route('public.home'))->assertOk()->assertSee('bottom-4 left-4', false);
    }

    public function test_every_page_offers_a_skip_link_and_a_labelled_main_region(): void
    {
        foreach ([
            route('public.home'),
            route('public.news.index'),
            route('public.services.index'),
            route('public.documents.index'),
            route('public.faq.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Lompat ke konten utama')
                ->assertSee('id="main-content"', false);
        }
    }

    public function test_navigation_landmarks_are_labelled(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('aria-label="Navigasi utama"', false);
    }

    public function test_the_admin_panel_also_offers_a_skip_link(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Super Admin');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Lompat ke konten utama')
            ->assertSee('id="main-content"', false);
    }
}
