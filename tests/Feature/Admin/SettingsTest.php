<?php

namespace Tests\Feature\Admin;

use App\Models\CarouselSlide;
use App\Models\HomepageSection;
use App\Models\SocialLink;
use App\Models\User;
use App\Services\Settings\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_every_settings_screen_renders(): void
    {
        foreach (['general', 'seo', 'accessibility', 'footer'] as $group) {
            $this->actingAs($this->admin)->get(route("admin.settings.$group.edit"))->assertOk();
        }

        foreach (['homepage.index', 'carousel.index', 'social.index'] as $route) {
            $this->actingAs($this->admin)->get(route("admin.settings.$route"))->assertOk();
        }
    }

    public function test_general_settings_are_saved_and_the_cache_is_refreshed(): void
    {
        $settings = app(SettingService::class);
        $settings->group('general');

        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), [
            'site_name' => 'Portal Dinas PUPR',
            'email' => 'humas@example.test',
        ])->assertRedirect();

        // Read back through the service: a stale cache would return the old name.
        $this->assertSame('Portal Dinas PUPR', $settings->get('general', 'site_name'));
    }

    public function test_an_unchecked_switch_is_stored_as_false(): void
    {
        $settings = app(SettingService::class);

        $this->assertTrue($settings->get('accessibility', 'text_to_speech_enabled'));

        // An unchecked box submits nothing at all.
        $this->actingAs($this->admin)->put(route('admin.settings.accessibility.update'), [
            'accessibility_enabled' => '1',
        ])->assertRedirect();

        $this->assertFalse($settings->get('accessibility', 'text_to_speech_enabled'));
        $this->assertTrue($settings->get('accessibility', 'accessibility_enabled'));
    }

    public function test_saving_without_reuploading_keeps_the_existing_logo(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), [
            'site_name' => 'Portal',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $logo = app(SettingService::class)->get('general', 'logo');
        $this->assertNotNull($logo);

        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), ['site_name' => 'Portal']);

        $this->assertSame($logo, app(SettingService::class)->get('general', 'logo'));
        Storage::disk('public')->assertExists($logo);
    }

    public function test_the_admin_sidebar_shows_the_uploaded_admin_logo(): void
    {
        // Falls back to the site logo while no admin-specific mark is set…
        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), [
            'site_name' => 'Portal',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $siteLogo = app(SettingService::class)->get('general', 'logo');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($siteLogo), false);

        // …and the admin mark takes over once uploaded.
        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), [
            'site_name' => 'Portal',
            'admin_logo' => UploadedFile::fake()->image('admin.png'),
        ]);

        $adminLogo = app(SettingService::class)->get('general', 'admin_logo');
        $this->assertNotNull($adminLogo);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($adminLogo), false);
    }

    public function test_a_carousel_slide_can_be_created(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.carousel.store'), [
            'title' => 'Selamat Datang',
            'image' => UploadedFile::fake()->image('slide.jpg'),
            'is_active' => 1,
        ])->assertRedirect(route('admin.settings.carousel.index'));

        $slide = CarouselSlide::first();

        $this->assertSame('Selamat Datang', $slide->title);
        Storage::disk('public')->assertExists($slide->image);
    }

    public function test_a_slide_window_that_ends_before_it_starts_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.carousel.store'), [
            'image' => UploadedFile::fake()->image('slide.jpg'),
            'start_date' => now()->addWeek()->format('Y-m-d\TH:i'),
            'end_date' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('end_date');
    }

    public function test_only_slides_inside_their_window_are_live(): void
    {
        CarouselSlide::create(['image' => 'a.jpg', 'is_active' => true]);
        CarouselSlide::create(['image' => 'b.jpg', 'is_active' => false]);
        CarouselSlide::create(['image' => 'c.jpg', 'is_active' => true, 'end_date' => now()->subDay()]);
        CarouselSlide::create(['image' => 'd.jpg', 'is_active' => true, 'start_date' => now()->addDay()]);

        $this->assertSame(['a.jpg'], CarouselSlide::live()->pluck('image')->all());
    }

    public function test_a_social_link_must_be_an_http_url(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.social.store'), [
            'platform' => 'Jahat',
            'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->assertSame(0, SocialLink::count());
    }

    public function test_any_social_platform_may_be_added(): void
    {
        // Deliberately includes a platform the code has never heard of: the
        // list is data, not an enum.
        foreach (['Facebook', 'TikTok', 'Mastodon', 'Jaringan Lokal'] as $platform) {
            $this->actingAs($this->admin)->post(route('admin.settings.social.store'), [
                'platform' => $platform,
                'url' => 'https://example.test/'.str($platform)->slug(),
                'is_active' => 1,
            ])->assertRedirect();
        }

        $this->assertSame(4, SocialLink::count());
    }

    public function test_homepage_sections_can_be_reordered(): void
    {
        $first = HomepageSection::create(['type' => 'hero', 'sort_order' => 10]);
        $second = HomepageSection::create(['type' => 'latest_news', 'sort_order' => 20]);
        $third = HomepageSection::create(['type' => 'services', 'sort_order' => 30]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.settings.homepage.reorder'), [
                'order' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            HomepageSection::orderBy('sort_order')->pluck('id')->all(),
        );
    }

    public function test_an_unknown_section_type_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.homepage.store'), [
            'type' => 'bagian_yang_tidak_ada',
        ])->assertSessionHasErrors('type');
    }

    public function test_a_viewer_cannot_change_settings(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('settings.view');

        $this->actingAs($viewer)->get(route('admin.settings.general.edit'))->assertOk();
        $this->actingAs($viewer)->put(route('admin.settings.general.update'), [
            'site_name' => 'Diubah paksa',
        ])->assertForbidden();

        $this->assertNotSame('Diubah paksa', app(SettingService::class)->get('general', 'site_name'));
    }
}
