<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
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

    public function test_a_service_can_be_created(): void
    {
        $category = Category::create(['type' => Category::TYPE_SERVICE, 'name' => 'Perizinan']);

        $this->actingAs($this->admin)->post(route('admin.services.store'), [
            'name' => 'Izin Mendirikan Bangunan',
            'category_id' => $category->id,
            'processing_time' => '14 hari kerja',
            'status' => Service::STATUS_PUBLISHED,
        ])->assertRedirect();

        $service = Service::firstWhere('slug', 'izin-mendirikan-bangunan');

        $this->assertNotNull($service);
        $this->assertSame('14 hari kerja', $service->processing_time);
        $this->assertSame($category->id, $service->category_id);
    }

    public function test_processing_time_accepts_any_wording(): void
    {
        foreach (['1 hari kerja', '1x24 jam', 'Maksimal 30 hari'] as $wording) {
            $service = Service::factory()->create(['processing_time' => $wording]);
            $this->assertSame($wording, $service->fresh()->processing_time);
        }
    }

    public function test_requirements_tariffs_and_steps_can_be_added_to_a_service(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.services.requirements.store', $service), [
                'name' => 'Fotokopi KTP', 'is_required' => 1, 'sort_order' => 10,
            ])->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.services.tariffs.store', $service), [
                'name' => 'Administrasi', 'amount' => 10000, 'is_active' => 1, 'sort_order' => 10,
            ])->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.services.steps.store', $service), [
                'name' => 'Pengajuan', 'sort_order' => 10,
            ])->assertRedirect();

        $service->refresh();

        $this->assertCount(1, $service->requirements);
        $this->assertCount(1, $service->tariffs);
        $this->assertCount(1, $service->steps);
    }

    public function test_the_total_tariff_sums_only_active_components(): void
    {
        $service = Service::factory()->create();

        $service->tariffs()->createMany([
            ['name' => 'Administrasi', 'amount' => 10000, 'is_active' => true],
            ['name' => 'Pemeriksaan', 'amount' => 15000, 'is_active' => true],
            ['name' => 'Komponen lama', 'amount' => 99000, 'is_active' => false],
        ]);

        $this->assertSame(25000.0, (float) $service->fresh()->totalTariff());
    }

    public function test_a_child_row_cannot_be_edited_through_another_service(): void
    {
        $owner = Service::factory()->create();
        $other = Service::factory()->create();

        $tariff = $owner->tariffs()->create(['name' => 'Administrasi', 'amount' => 10000]);

        // Same tariff id, wrong parent in the URL.
        $this->actingAs($this->admin)
            ->get(route('admin.services.tariffs.edit', [$other, $tariff->id]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.services.tariffs.destroy', [$other, $tariff->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('service_tariffs', ['id' => $tariff->id]);
    }

    public function test_the_service_screens_render(): void
    {
        $service = Service::factory()->create();

        foreach ([
            route('admin.services.index'),
            route('admin.services.create'),
            route('admin.services.edit', $service),
            route('admin.services.requirements.index', $service),
            route('admin.services.tariffs.index', $service),
            route('admin.services.steps.index', $service),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_a_viewer_cannot_modify_services(): void
    {
        $service = Service::factory()->create();

        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('admin.services.create'))->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('admin.services.tariffs.store', $service), ['name' => 'X', 'amount' => 1])
            ->assertForbidden();
    }
}
