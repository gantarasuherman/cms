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

    public function test_public_buttons_carry_no_colour_of_their_own(): void
    {
        // Every *control* on a public page takes its fill from the theme
        // variables. A literal here would survive every colour change and
        // leave one button stuck on the palette it was written against —
        // which is exactly how the old news accent outlived two rebrands.
        //
        // Scoped to controls on purpose: the hero's dark backdrop behind
        // photography is a literal and should stay one, because it is not a
        // brand surface but the ground a white headline has to read against.
        $views = [
            'resources/views/components/public/hero-slider.blade.php',
            'resources/views/public/news/index.blade.php',
            'resources/views/public/news/partials/sidebar.blade.php',
        ];

        foreach ($views as $view) {
            foreach (file(base_path($view)) as $number => $line) {
                if (! str_contains($line, '<a ') && ! str_contains($line, '<button') && ! str_contains($line, 'class=')) {
                    continue;
                }

                if (! preg_match('/(bg|border|text)-\[#[0-9A-Fa-f]{3,8}\]/', $line, $found)) {
                    continue;
                }

                // A control is a line that also names a control-ish shape.
                if (! preg_match('/rounded|px-|py-/', $line)) {
                    continue;
                }

                $this->fail($view.':'.($number + 1).' memakai warna tetap '.$found[0].', bukan warna dari panel admin.');
            }
        }

        $this->assertTrue(true);
    }

    public function test_a_button_follows_the_colour_an_administrator_picks(): void
    {
        $this->save(['primary_color' => '#7C1D6F', 'secondary_color' => '#16A34A'])->assertRedirect();

        $content = str_replace(' ', '', $this->get(route('public.home'))->assertOk()->getContent());

        // The variables every button class resolves against.
        $this->assertStringContainsString('--brand-primary:#7c1d6f', $content);
        $this->assertStringContainsString('--brand-secondary:#16a34a', $content);
        $this->assertStringContainsString('--brand-on-primary:', $content);
        $this->assertStringContainsString('--brand-on-secondary:', $content);
    }

    public function test_a_pale_button_colour_gets_dark_lettering(): void
    {
        // White on #FFD166 is 1.44:1 — invisible. The ink is measured, not
        // assumed, so a pale pick flips it to dark instead of erasing it.
        $this->save(['primary_color' => '#FFD166', 'secondary_color' => '#FFF3BF'])->assertRedirect();

        $theme = $this->theme()->variables();

        foreach (['--brand-on-primary' => '--brand-primary', '--brand-on-secondary' => '--brand-secondary'] as $ink => $fill) {
            $this->assertGreaterThanOrEqual(
                Color::AA,
                Color::contrast($theme[$ink], $theme[$fill]),
                $ink.' tidak terbaca di atas '.$fill.'.',
            );
        }
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
