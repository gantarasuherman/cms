<?php

namespace Tests\Feature\Public;

use App\Models\Setting;
use App\Services\Settings\SettingService;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderTest extends TestCase
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

    private function setting(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['group' => 'general', 'key' => $key], ['value' => $value, 'type' => 'string']);
        app(SettingService::class)->forget();
    }

    public function test_the_site_name_is_not_printed_beside_the_logo(): void
    {
        $content = $this->get(route('public.home'))->assertOk()->getContent();
        $header = substr($content, strpos($content, '<header'), strpos($content, '</header>') - strpos($content, '<header'));

        // Present for a screen reader, never as visible lettering.
        $this->assertStringContainsString('class="sr-only">Dynamic CMS — beranda', $header);
        $this->assertStringNotContainsString('text-base font-bold text-slate-900">Dynamic CMS', $header);
    }

    public function test_the_header_carries_a_search_field(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('role="search"', false)
            ->assertSee('name="jenis"', false)
            ->assertSee('Cari berita, layanan, atau dokumen…', false);
    }

    public function test_the_header_search_posts_where_the_homepage_search_does(): void
    {
        // Both controls must mean the same thing, or a query typed in one
        // place would behave differently from the same query typed elsewhere.
        $this->get(route('public.search', ['q' => 'jalan', 'jenis' => 'berita']))
            ->assertOk()
            ->assertSee('jalan');
    }

    public function test_the_call_to_action_appears_only_when_both_halves_are_set(): void
    {
        $this->get(route('public.home'))->assertOk()->assertDontSee('data-header-cta', false);

        $this->setting('header_cta_text', 'Lapor Pengaduan');
        $this->get(route('public.home'))->assertOk()->assertDontSee('Lapor Pengaduan');

        $this->setting('header_cta_link', '/halaman/pengaduan');
        $this->get(route('public.home'))->assertOk()->assertSee('Lapor Pengaduan');
    }

    public function test_a_scripted_call_to_action_link_is_refused(): void
    {
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->put(route('admin.settings.general.update'), [
                'site_name' => 'Dynamic CMS',
                'header_cta_text' => 'Klik',
                'header_cta_link' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('header_cta_link');
    }

    public function test_nested_menu_items_render_as_disclosures(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Infrastruktur');
    }
}
