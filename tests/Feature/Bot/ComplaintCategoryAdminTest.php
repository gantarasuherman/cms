<?php

namespace Tests\Feature\Bot;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jenis pengaduan beserta syarat buktinya, diatur dari panel.
 *
 * Sebelum layar ini ada, `requires_photo` dan `requires_location` hanya bisa
 * diubah lewat seeder — padahal justru itu keputusan yang paling sering perlu
 * disesuaikan setelah situs berjalan.
 */
class ComplaintCategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    /* ------------------------------------------------------------- screens */

    public function test_the_screens_render(): void
    {
        $category = ComplaintCategory::where('slug', 'jembatan')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.complaint-categories.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.complaint-categories.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.complaint-categories.edit', $category))->assertOk();
    }

    public function test_the_list_says_what_each_kind_asks_for(): void
    {
        $this->actingAs($this->admin)->get(route('admin.complaint-categories.index'))
            ->assertOk()
            ->assertSee('Titik lokasi')
            // Jenis tanpa syarat harus terbaca sebagai kalimat, bukan kolom kosong.
            ->assertSee('Cukup uraian');
    }

    /* ------------------------------------------------------------- writing */

    public function test_a_kind_is_created_with_its_evidence_rules(): void
    {
        $this->actingAs($this->admin)->post(route('admin.complaint-categories.store'), [
            'name' => 'Penerangan Jalan Umum',
            'description' => 'Lampu jalan mati atau rusak.',
            'sort_order' => 90,
            'requires_photo' => 1,
            'requires_location' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('admin.complaint-categories.index'));

        $category = ComplaintCategory::where('slug', 'penerangan-jalan-umum')->firstOrFail();

        $this->assertTrue($category->requires_photo);
        $this->assertTrue($category->requires_location);
        $this->assertSame(['image', 'location'], $category->evidenceRequired());
    }

    public function test_unticking_both_leaves_a_kind_that_only_needs_words(): void
    {
        $category = ComplaintCategory::where('slug', 'jembatan')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.complaint-categories.update', $category), [
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_active' => 1,
            // requires_photo dan requires_location tidak dikirim sama sekali,
            // seperti formulir yang kedua kotaknya dilepas centangnya.
        ])->assertRedirect(route('admin.complaint-categories.index'));

        $this->assertSame([], $category->fresh()->evidenceRequired());
    }

    public function test_renaming_never_moves_the_slug(): void
    {
        $category = ComplaintCategory::where('slug', 'jembatan')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.complaint-categories.update', $category), [
            'name' => 'Jembatan dan Gorong-gorong',
            'sort_order' => $category->sort_order,
            'is_active' => 1,
        ])->assertRedirect();

        // Node alur percakapan merujuk jenis ini dengan slug-nya; menggesernya
        // memutus rujukan itu tanpa ada yang terlihat salah di layar mana pun.
        $this->assertSame('jembatan', $category->fresh()->slug);
        $this->assertSame('Jembatan dan Gorong-gorong', $category->fresh()->name);
    }

    /* -------------------------------------------------------------- guards */

    public function test_a_kind_still_holding_complaints_is_not_deleted(): void
    {
        $category = ComplaintCategory::where('slug', 'irigasi')->firstOrFail();

        Complaint::create([
            'ticket' => 'ADU-KEEP0001', 'complaint_category_id' => $category->getKey(),
            'channel' => 'telegram', 'description' => 'Saluran tersumbat.', 'status' => 'baru',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.complaint-categories.destroy', $category))
            ->assertRedirect();

        // Laporan tanpa jenis tidak dapat disalurkan ke petugas mana pun.
        $this->assertDatabaseHas('complaint_categories', ['id' => $category->getKey()]);
    }

    public function test_an_unused_kind_can_be_deleted(): void
    {
        $category = ComplaintCategory::where('slug', 'bangunan')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.complaint-categories.destroy', $category))
            ->assertRedirect(route('admin.complaint-categories.index'));

        $this->assertDatabaseMissing('complaint_categories', ['id' => $category->getKey()]);
    }

    public function test_somebody_without_chatbot_rights_cannot_change_the_rules(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('admin.complaint-categories.index'))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.complaint-categories.store'), [
            'name' => 'Penyusup', 'sort_order' => 10,
        ])->assertForbidden();
    }
}
