<?php

namespace Tests\Feature\Admin;

use App\Models\CarouselSlide;
use App\Models\User;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSliderTest extends TestCase
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
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function slide(array $attributes = []): CarouselSlide
    {
        return CarouselSlide::create($attributes + [
            'title' => 'Infrastruktur Terus Dibenahi',
            'category' => 'Infrastruktur',
            'description' => 'Peningkatan infrastruktur secara bertahap.',
            'image' => 'carousel/contoh.jpg',
            'alt_text' => 'Pekerja di lokasi pembangunan',
            'button_text' => 'Selengkapnya',
            'link' => '/berita',
            'is_active' => true,
        ]);
    }

    /* ------------------------------------------------------------- admin */

    public function test_the_slide_screens_render(): void
    {
        $slide = $this->slide();

        foreach ([
            route('admin.settings.carousel.index'),
            route('admin.settings.carousel.create'),
            route('admin.settings.carousel.edit', $slide),
            route('admin.settings.carousel.preview'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_the_datatable_carries_the_columns_the_listing_shows(): void
    {
        $this->slide(['category' => 'Jalan']);

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.settings.carousel.data').'?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data.0');

        foreach (['preview', 'title', 'category', 'status', 'sort_order', 'start_date', 'end_date', 'actions'] as $column) {
            $this->assertArrayHasKey($column, $row);
        }

        $this->assertSame('Jalan', $row['category']);
    }

    public function test_a_slide_can_be_created_with_every_hero_field(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.carousel.store'), [
            'category' => 'Irigasi',
            'title' => 'Menjaga Infrastruktur Irigasi',
            'subtitle' => 'Sumber daya air',
            'description' => 'Pemeliharaan jaringan irigasi.',
            'alt_text' => 'Saluran irigasi',
            'button_text' => 'Lihat Informasi',
            'link' => '/berita',
            'image' => UploadedFile::fake()->image('hero.jpg', 1920, 1080),
            'is_active' => 1,
        ])->assertRedirect(route('admin.settings.carousel.index'));

        $slide = CarouselSlide::firstWhere('title', 'Menjaga Infrastruktur Irigasi');

        $this->assertSame('Irigasi', $slide->category);
        $this->assertSame('Saluran irigasi', $slide->alt_text);
        $this->assertSame('Pemeliharaan jaringan irigasi.', $slide->description);
        Storage::disk('public')->assertExists($slide->image);
    }

    public function test_a_new_slide_goes_to_the_end_of_the_order(): void
    {
        $this->slide(['sort_order' => 40]);

        $this->actingAs($this->admin)->post(route('admin.settings.carousel.store'), [
            'title' => 'Slide Baru',
            'image' => UploadedFile::fake()->image('hero.jpg'),
        ]);

        $this->assertSame(50, CarouselSlide::firstWhere('title', 'Slide Baru')->sort_order);
    }

    public function test_duplicating_copies_the_file_and_leaves_the_copy_switched_off(): void
    {
        Storage::disk('public')->put('carousel/asli.jpg', 'isi');
        $slide = $this->slide(['image' => 'carousel/asli.jpg']);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.carousel.duplicate', $slide))
            ->assertRedirect();

        $copy = CarouselSlide::where('id', '!=', $slide->id)->firstOrFail();

        $this->assertFalse($copy->is_active, 'Salinan tidak boleh langsung tayang.');
        $this->assertNotSame($slide->image, $copy->image, 'Berkas harus ikut disalin, bukan dipakai bersama.');
        Storage::disk('public')->assertExists($copy->image);
        Storage::disk('public')->assertExists($slide->image);
    }

    public function test_deleting_a_duplicate_leaves_the_original_image_intact(): void
    {
        Storage::disk('public')->put('carousel/asli.jpg', 'isi');
        $slide = $this->slide(['image' => 'carousel/asli.jpg']);

        $this->actingAs($this->admin)->post(route('admin.settings.carousel.duplicate', $slide));
        $copy = CarouselSlide::where('id', '!=', $slide->id)->firstOrFail();

        $this->actingAs($this->admin)->delete(route('admin.settings.carousel.destroy', $copy));

        // This is the reason the file is copied rather than shared.
        Storage::disk('public')->assertExists($slide->image);
    }

    public function test_a_slide_can_be_toggled(): void
    {
        $slide = $this->slide(['is_active' => true]);

        $this->actingAs($this->admin)
            ->patch(route('admin.settings.carousel.toggle', $slide))
            ->assertRedirect();

        $this->assertFalse($slide->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.settings.carousel.toggle', $slide));
        $this->assertTrue($slide->fresh()->is_active);
    }

    public function test_slides_can_be_reordered(): void
    {
        $first = $this->slide(['title' => 'Satu', 'sort_order' => 10]);
        $second = $this->slide(['title' => 'Dua', 'sort_order' => 20]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.settings.carousel.reorder'), ['order' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
    }

    public function test_the_preview_shows_slides_the_public_cannot_see_yet(): void
    {
        $this->slide(['title' => 'Belum Aktif', 'is_active' => false]);

        // The point of a preview is to check a slide before switching it on.
        $this->actingAs($this->admin)
            ->get(route('admin.settings.carousel.preview'))
            ->assertOk()
            ->assertSee('Belum Aktif');
    }

    public function test_a_viewer_cannot_change_slides(): void
    {
        $slide = $this->slide();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('settings.view');

        $this->actingAs($viewer)->patch(route('admin.settings.carousel.toggle', $slide))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.settings.carousel.duplicate', $slide))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.settings.carousel.reorder'), ['order' => [$slide->id]])->assertForbidden();
    }

    /* ------------------------------------------------------------ public */

    public function test_the_hero_opens_the_homepage(): void
    {
        $this->slide();

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $hero = strpos($content, 'data-hero');
        $main = strpos($content, 'id="main-content"');

        $this->assertNotFalse($hero);
        $this->assertGreaterThan($main, $hero, 'Hero berada di dalam konten utama.');
        $this->assertStringContainsString('Infrastruktur Terus Dibenahi', $content);
    }

    public function test_only_live_slides_reach_the_public(): void
    {
        $this->slide(['title' => 'Tayang']);
        $this->slide(['title' => 'Nonaktif', 'is_active' => false]);
        $this->slide(['title' => 'Terjadwal', 'start_date' => now()->addWeek()]);
        $this->slide(['title' => 'Kedaluwarsa', 'end_date' => now()->subDay()]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Tayang')
            ->assertDontSee('Nonaktif')
            ->assertDontSee('Terjadwal')
            ->assertDontSee('Kedaluwarsa');
    }

    public function test_the_hero_announces_itself_and_can_be_paused(): void
    {
        $this->slide(['title' => 'Satu']);
        $this->slide(['title' => 'Dua']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('role="tablist"', false)
            // Autoplay needs a real stop control, not just hover (WCAG 2.2.2).
            ->assertSee('data-hero-play', false)
            ->assertSee('Hentikan pergantian otomatis');
    }

    public function test_the_first_image_is_prioritised_and_the_rest_deferred(): void
    {
        $this->slide(['title' => 'Satu']);
        $this->slide(['title' => 'Dua']);

        $content = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, 'fetchpriority="high"'), 'Hanya slide pertama yang diprioritaskan.');
        $this->assertStringContainsString('loading="lazy"', $content);
    }

    public function test_a_slide_without_alt_text_renders_an_empty_alt(): void
    {
        // The heading beside it carries the meaning; repeating it as alt text
        // would have the same sentence announced twice.
        $this->slide(['alt_text' => null]);

        $this->get(route('public.home'))->assertOk()->assertSee('alt=""', false);
    }

    public function test_the_api_exposes_the_hero_fields(): void
    {
        $this->slide();

        $this->getJson(route('api.public.carousel'))
            ->assertOk()
            ->assertJsonPath('data.0.category', 'Infrastruktur')
            ->assertJsonPath('data.0.alt_text', 'Pekerja di lokasi pembangunan')
            ->assertJsonStructure(['data' => [['id', 'category', 'title', 'description', 'image', 'link', 'button_text']]]);
    }
}
