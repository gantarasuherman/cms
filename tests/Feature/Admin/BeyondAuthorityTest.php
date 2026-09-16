<?php

namespace Tests\Feature\Admin;

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
 * "Bukan Kewenangan Dinas" pada ketiga layar yang membacanya.
 *
 * Statusnya sudah ada sejak alur `/bukan` dibuat, tetapi hanya hidup di dalam
 * chat petugas: dasbor tidak menyebut pengaduan sama sekali, dan kedua layar
 * pengaduan melebur angkanya ke dalam "Ditolak". Yang dijaga di sini adalah
 * perbedaan itu — laporan yang bukan kewenangan dinas ini bukanlah laporan
 * yang ditolak, dan menyamakan keduanya menyembunyikan satu-satunya angka yang
 * menjawab "berapa banyak yang sebenarnya bukan pekerjaan kami".
 */
class BeyondAuthorityTest extends TestCase
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

    private function complaint(string $status, bool $located = false): Complaint
    {
        return Complaint::create([
            'ticket' => 'ADU-'.strtoupper(str()->random(8)),
            'complaint_category_id' => ComplaintCategory::where('slug', 'jalan')->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Contoh laporan.',
            'status' => $status,
            'latitude' => $located ? -6.33 : null,
            'longitude' => $located ? 108.32 : null,
        ]);
    }

    /* --------------------------------------------------------------- kata */

    public function test_the_status_is_named_for_what_it_means(): void
    {
        // "Diteruskan" menjanjikan sesuatu yang tidak dikerjakan dinas ini:
        // bot tidak pernah menghubungi instansi tujuan, ia hanya memberi tahu
        // warga ke mana harus menghubungi.
        $this->assertSame('Bukan Kewenangan Dinas', Complaint::STATUSES['diteruskan']);

        // Kuncinya tidak boleh ikut berubah — barisnya sudah tersimpan begitu.
        $this->assertSame('diteruskan', $this->complaint('diteruskan')->status);
    }

    public function test_the_badge_does_not_look_like_a_rejection(): void
    {
        $complaint = $this->complaint('diteruskan');

        $badge = view('admin.complaints.partials.status', ['complaint' => $complaint])->render();
        $rejected = view('admin.complaints.partials.status', ['complaint' => $this->complaint('ditolak')])->render();

        $this->assertStringContainsString('Bukan Kewenangan Dinas', $badge);
        // Dua hal yang berbeda tidak boleh terbaca sama sekilas pandang.
        $this->assertNotSame(
            strip_tags($rejected),
            strip_tags($badge),
            'Lencana "bukan kewenangan" seharusnya berbeda dari lencana "ditolak".',
        );
    }

    /* ----------------------------------------------------------- dasbor */

    public function test_the_dashboard_counts_it_separately(): void
    {
        $this->complaint('diteruskan');
        $this->complaint('diteruskan');
        $this->complaint('ditolak');
        $this->complaint('baru');

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Bukan kewenangan');

        $stats = $response->viewData('complaints');

        $this->assertSame(2, $stats['byStatus']['diteruskan']);
        // Bukan dilebur: yang ditolak tetap satu, bukan tiga.
        $this->assertSame(1, $stats['byStatus']['ditolak']);
    }

    public function test_the_dashboard_says_nothing_about_complaints_to_those_who_may_not_see_them(): void
    {
        $this->complaint('diteruskan');

        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));

        $response->assertOk();
        // Angkanya tidak dihitung sama sekali, bukan sekadar disembunyikan —
        // dan tombol menuju halaman yang akan menolaknya pun tidak ada.
        $this->assertNull($response->viewData('complaints'));
        $response->assertDontSee('Bukan kewenangan');
    }

    public function test_the_dashboard_survives_a_month_without_complaints(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Belum ada pengaduan masuk');
    }

    /* -------------------------------------------------------- pengaduan */

    public function test_the_complaints_page_gives_it_its_own_tile(): void
    {
        $this->complaint('diteruskan');
        $this->complaint('ditolak');

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.index'));

        $response->assertOk();
        $response->assertSee('Bukan kewenangan');
        $response->assertSee('Warga diarahkan ke instansi lain');

        $this->assertSame(1, $response->viewData('stats')['byStatus']['diteruskan']);
    }

    public function test_the_complaints_page_can_be_filtered_down_to_it(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.complaints.index'));

        // Saringan status membaca Complaint::STATUSES, jadi pilihannya harus
        // ikut memakai kata yang sama dengan kartunya.
        $response->assertSee('value="diteruskan"', false);
        $response->assertSee('Bukan Kewenangan Dinas');
    }

    /* -------------------------------------------------------------- peta */

    public function test_the_map_counts_the_points_that_are_not_ours(): void
    {
        $this->complaint('diteruskan', located: true);
        $this->complaint('diteruskan', located: true);
        $this->complaint('baru', located: true);
        // Tanpa titik lokasi: tidak tampil di peta, jadi tidak ikut dihitung.
        $this->complaint('diteruskan');

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.map'));

        $response->assertOk();
        $response->assertSee('Bukan kewenangan dinas');
        $this->assertSame(2, $response->viewData('redirected'));
        $this->assertSame(3, $response->viewData('located'));
    }

    public function test_the_map_count_follows_the_filter(): void
    {
        $this->complaint('diteruskan', located: true);
        $this->complaint('baru', located: true);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.complaints.map', ['status' => 'baru']));

        $response->assertOk();
        // Kartunya berdiri di antara kartu lain yang semuanya berbicara tentang
        // peta yang sedang dilihat; satu angka yang diam-diam mengabaikan
        // saringan membuat kartu di sebelahnya ikut tampak tidak dapat
        // dipercaya.
        $this->assertSame(0, $response->viewData('redirected'));
    }
}
