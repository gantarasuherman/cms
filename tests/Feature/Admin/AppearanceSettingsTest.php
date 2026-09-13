<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingService;
use App\Services\Theme\ThemeService;
use App\Support\Color;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
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

    private function save(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('admin.settings.appearance.update'), $overrides + [
            'primary_color' => '#7c3aed',
            'secondary_color' => '#EF8519',
            'footer_color' => '#F8FAFC',
            'font_family' => 'serif',
        ]);
    }

    private function theme(): ThemeService
    {
        app(SettingService::class)->forget();

        return app(ThemeService::class);
    }

    /* ------------------------------------------------------------- screen */

    public function test_the_appearance_screen_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.appearance.edit'))
            ->assertOk()
            ->assertSee('Warna Utama')
            ->assertSee('Jenis Huruf')
            ->assertSee('name="primary_color"', false);
    }

    public function test_it_appears_in_the_admin_menu(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.general.edit'))
            ->assertOk()
            ->assertSee(route('admin.settings.appearance.edit'), false);
    }

    /* -------------------------------------------------------------- saving */

    public function test_a_chosen_colour_and_font_are_stored(): void
    {
        $this->save()->assertRedirect();

        $theme = $this->theme();

        $this->assertSame('#7c3aed', $theme->primary());
        $this->assertSame(ThemeService::FONTS['serif']['stack'], $theme->font()['stack']);
    }

    public function test_a_malformed_colour_is_refused(): void
    {
        foreach (['merah', '#12345', 'rgb(1,2,3)', '', '#fff;}body{display:none}'] as $bad) {
            $this->save(['primary_color' => $bad])->assertSessionHasErrors('primary_color');
        }

        // Rejected, so the seeded value still stands.
        $this->assertSame(Color::normalise(ThemeService::DEFAULT_PRIMARY), $this->theme()->primary());
    }

    public function test_a_font_outside_the_offered_list_is_refused(): void
    {
        // Otherwise an arbitrary family string would reach a CSS declaration.
        $this->save(['font_family' => 'Comic Sans MS'])->assertSessionHasErrors('font_family');
        $this->save(['font_family' => "Inter'; background: url(https://evil.test)"])->assertSessionHasErrors('font_family');
    }

    public function test_a_viewer_cannot_change_the_theme(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('settings.view');

        $this->actingAs($viewer)
            ->put(route('admin.settings.appearance.update'), ['primary_color' => '#000000'])
            ->assertForbidden();
    }

    /* ------------------------------------------------------------- falling back */

    public function test_a_corrupt_stored_value_falls_back_instead_of_leaving_the_site_unpainted(): void
    {
        // Written straight to the table, bypassing the form's validation — the
        // read side has to defend itself too.
        Setting::where('group', 'appearance')->where('key', 'primary_color')->update(['value' => 'not-a-colour']);

        $this->assertSame(Color::normalise(ThemeService::DEFAULT_PRIMARY), $this->theme()->primary());
    }

    public function test_an_unknown_stored_font_falls_back_to_the_default(): void
    {
        Setting::where('group', 'appearance')->where('key', 'font_family')->update(['value' => 'wingdings']);

        $this->assertSame(ThemeService::FONTS[ThemeService::DEFAULT_FONT], $this->theme()->font());
    }

    /* ------------------------------------------------------------- the css */

    public function test_the_public_layout_carries_the_theme(): void
    {
        $this->save(['primary_color' => '#7c3aed'])->assertRedirect();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertStringContainsString('--color-teal-700:#7c3aed', $content);
        $this->assertStringContainsString('--brand-primary:#7c3aed', $content);
        // Doubled selector, so Tailwind's own :root cannot win on source order.
        $this->assertStringContainsString(':root:root{', $content);
    }

    public function test_the_theme_names_a_text_colour_that_actually_passes(): void
    {
        // A pale pick is the case that matters: white lettering on it fails.
        $this->save(['primary_color' => '#f4c430'])->assertRedirect();

        $variables = $this->theme()->variables();

        $this->assertSame('#0b1b2b', $variables['--brand-on-primary']);
        $this->assertGreaterThanOrEqual(
            Color::AA,
            Color::contrast('#f4c430', $variables['--brand-on-primary']),
        );
        $this->assertGreaterThanOrEqual(
            Color::AA,
            Color::contrast($variables['--brand-primary-ink'], '#ffffff'),
        );
    }

    public function test_nothing_in_the_style_block_can_close_it(): void
    {
        Setting::where('group', 'appearance')->update(['value' => '</style><script>alert(1)</script>']);
        app(SettingService::class)->forget();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringContainsString('--color-teal-700:'.Color::normalise(ThemeService::DEFAULT_PRIMARY), $content);
    }

    public function test_the_footer_colour_is_stored_and_applied(): void
    {
        $this->save(['footer_color' => '#0F172A'])->assertRedirect();

        $this->assertSame('#0f172a', $this->theme()->footer());

        $this->get(route('public.home'))->assertOk()->assertSee('--footer-bg:#0f172a', false);
    }

    public function test_footer_text_stays_legible_on_any_footer_colour(): void
    {
        // The reason these are derived: a fixed slate would vanish on a dark
        // ground and a fixed white would vanish on a pale one.
        foreach (['#0F172A', '#F8FAFC', '#02468B', '#f4c430', '#ffffff', '#000000'] as $ground) {
            $this->save(['footer_color' => $ground])->assertRedirect();
            $variables = $this->theme()->variables();

            foreach (['--footer-ink', '--footer-muted', '--footer-link'] as $role) {
                $this->assertGreaterThanOrEqual(
                    Color::AA,
                    Color::contrast($variables['--footer-bg'], $variables[$role]),
                    "{$role} fails on {$ground}.",
                );
            }
        }
    }

    public function test_a_malformed_footer_colour_is_refused(): void
    {
        $this->save(['footer_color' => 'biru tua'])->assertSessionHasErrors('footer_color');
    }

    public function test_the_footer_carries_no_hard_coded_palette(): void
    {
        $this->save(['footer_color' => '#0F172A'])->assertRedirect();

        $content = $this->get(route('public.home'))->assertOk()->getContent();
        $start = strpos($content, '<footer');
        $footer = substr($content, $start, strpos($content, '</footer>') - $start);

        // A stray slate utility here would survive every colour change and
        // quietly become unreadable.
        $this->assertDoesNotMatchRegularExpression('/(text|bg|border)-slate-\d/', $footer);
    }

    public function test_every_offered_font_is_a_local_or_system_stack(): void
    {
        foreach (ThemeService::FONTS as $key => $font) {
            // A remote font file would send every visitor's IP to a third
            // party, which is the same reason the analytics store none.
            $this->assertStringNotContainsString('http', $font['stack'], $key);
            $this->assertStringNotContainsString('url(', $font['stack'], $key);
            $this->assertStringNotContainsString('@import', $font['stack'], $key);
        }
    }
}
