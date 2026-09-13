<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Icon;
use App\Models\User;
use App\Services\Icons\IconRepository;
use Database\Seeders\IconSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IconBankTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(IconSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_the_generated_set_is_dumped_into_the_database(): void
    {
        $configured = array_keys(config('icons'));

        $this->assertNotEmpty($configured);
        $this->assertSame(count($configured), Icon::count());

        foreach (['house', 'newspaper', 'briefcase', 'circle'] as $name) {
            $this->assertDatabaseHas('icons', ['name' => $name, 'is_active' => true]);
        }
    }

    public function test_every_icon_is_grouped_for_the_picker(): void
    {
        $groups = app(IconRepository::class)->grouped();

        $this->assertGreaterThan(1, $groups->count());
        // Nothing may be left ungrouped, or it would be unreachable in the list.
        $this->assertSame(Icon::count(), $groups->flatten(1)->count());
    }

    public function test_reseeding_keeps_existing_icons_usable(): void
    {
        $before = Icon::firstWhere('name', 'house');

        $this->seed(IconSeeder::class);

        // Matched by name, so a menu already pointing at this icon still works.
        $this->assertSame($before->id, Icon::firstWhere('name', 'house')->id);
        $this->assertSame(count(config('icons')), Icon::count());
    }

    public function test_an_icon_dropped_from_the_set_is_deactivated_not_deleted(): void
    {
        Icon::create([
            'name' => 'ikon-lama', 'label' => 'Ikon Lama', 'group' => 'Lainnya',
            'body' => '<circle cx="12" cy="12" r="10" />', 'is_active' => true,
        ]);

        $this->seed(IconSeeder::class);

        // Deleting it would break anything still referring to it.
        $this->assertDatabaseHas('icons', ['name' => 'ikon-lama', 'is_active' => false]);
    }

    /* --------------------------------------------------------------- picker */

    public function test_the_form_offers_a_dropdown_not_a_text_field(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.news.category.create'))
            ->assertOk();

        $response->assertSee('data-icon-select', false)
            ->assertSee('<optgroup', false)
            ->assertSee('data-icon-preview-for', false);

        // The old free-text field is gone.
        $this->assertStringNotContainsString('name="icon" type="text"', $response->getContent());
    }

    public function test_an_icon_outside_the_bank_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.category.store'), [
                'name' => 'Kategori Uji',
                'icon' => 'ikon-yang-tidak-ada',
            ])
            ->assertSessionHasErrors('icon');

        $this->assertSame(0, Category::count());
    }

    public function test_an_icon_from_the_bank_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.news.category.store'), [
                'name' => 'Kategori Uji',
                'icon' => 'newspaper',
            ])
            ->assertRedirect();

        $this->assertSame('newspaper', Category::firstWhere('name', 'Kategori Uji')->icon);
    }

    /* ------------------------------------------------------------ rendering */

    public function test_an_unknown_name_falls_back_instead_of_rendering_nothing(): void
    {
        $icons = app(IconRepository::class);

        $this->assertFalse($icons->has('tidak-ada'));
        // A hole in the interface is worse than a neutral glyph.
        $this->assertSame($icons->body('circle'), $icons->body('tidak-ada'));
    }

    public function test_rendering_survives_an_empty_icon_table(): void
    {
        Icon::query()->delete();
        app(IconRepository::class)->forget();
        Cache::flush();

        // Falls back to config/icons.php rather than drawing blanks.
        $this->assertNotEmpty(app(IconRepository::class)->body('house'));

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_the_preview_endpoint_returns_only_catalogued_artwork(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.icons.show', 'newspaper'))
            ->assertOk()
            ->assertJsonPath('name', 'newspaper')
            ->assertJsonStructure(['name', 'body']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.icons.show', 'tidak-ada'))
            ->assertNotFound();
    }

    public function test_the_preview_endpoint_requires_authentication(): void
    {
        $this->get(route('admin.icons.show', 'newspaper'))->assertRedirect(route('admin.login'));
    }
}
