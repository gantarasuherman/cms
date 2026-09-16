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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Saringan pada peta pengaduan.
 *
 * Peta menjawab "di mana hal yang sama dilaporkan berulang kali"; saringan
 * jenis dan rentang tanggal yang membuat pertanyaan itu bisa dipersempit —
 * titik yang dilaporkan sepanjang musim hujan menceritakan hal yang berbeda
 * dari titik yang dilaporkan sekali tahun lalu.
 */
class ComplaintMapFilterTest extends TestCase
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

    private function complaint(string $slug, int $daysAgo = 0, float $lat = -6.33, float $lng = 108.32): Complaint
    {
        $complaint = Complaint::create([
            'ticket' => 'ADU-'.strtoupper(str()->random(8)),
            'complaint_category_id' => ComplaintCategory::where('slug', $slug)->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Contoh laporan berlokasi.',
            'status' => 'baru',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        if ($daysAgo > 0) {
            $complaint->newQuery()->whereKey($complaint->getKey())
                ->update(['created_at' => now()->subDays($daysAgo)]);
        }

        return $complaint;
    }

    /** Titik-titik yang diserahkan halaman ke peta. */
    private function points(string $query = ''): array
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.complaints.map').($query ? '?'.$query : ''));

        $response->assertOk();

        return $response->viewData('points')->all();
    }

    private function located(string $query = ''): int
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.complaints.map').($query ? '?'.$query : ''));

        $response->assertOk();

        return $response->viewData('located');
    }

    /* ------------------------------------------------------------- jenis */

    public function test_the_map_can_be_narrowed_to_one_kind(): void
    {
        $this->complaint('jalan');
        $this->complaint('jalan');
        $this->complaint('jembatan');

        $jalan = ComplaintCategory::where('slug', 'jalan')->value('id');

        $this->assertSame(3, $this->located());
        $this->assertSame(2, $this->located('kategori='.$jalan));
    }

    public function test_complaints_without_a_pin_never_reach_the_map(): void
    {
        $this->complaint('jalan');

        Complaint::create([
            'ticket' => 'ADU-NOPIN001',
            'complaint_category_id' => ComplaintCategory::where('slug', 'lainnya')->value('id'),
            'channel' => 'whatsapp', 'description' => 'Tanpa titik lokasi.', 'status' => 'baru',
        ]);

        // Sebuah peta yang menghitung laporan tanpa koordinat akan menjanjikan
        // titik yang tidak pernah bisa ditampilkannya.
        $this->assertSame(1, $this->located());
    }

    /* ---------------------------------------------------------- periode */

    public function test_a_date_range_leaves_older_reports_out(): void
    {
        $this->complaint('jalan', 1);
        $this->complaint('jalan', 200);

        $this->assertSame(2, $this->located());
        $this->assertSame(1, $this->located('dari='.now()->subDays(7)->format('Y-m-d')));
    }

    public function test_the_closing_date_includes_that_whole_day(): void
    {
        $this->complaint('jalan', 0);

        // Tanggal "sampai" yang berhenti di tengah malam awal hari akan
        // membuang seluruh laporan hari itu — saringan yang tampak benar dan
        // diam-diam kehilangan sehari penuh.
        $this->assertSame(1, $this->located('sampai='.now()->format('Y-m-d')));
    }

    public function test_both_ends_can_be_given_at_once(): void
    {
        $this->complaint('jalan', 10);
        $this->complaint('jalan', 60);

        $query = 'dari='.now()->subDays(30)->format('Y-m-d').'&sampai='.now()->format('Y-m-d');

        $this->assertSame(1, $this->located($query));
    }

    /* ------------------------------------------------------------ galat */

    public function test_an_unreadable_date_is_ignored_rather_than_breaking_the_map(): void
    {
        $this->complaint('jalan');

        // Peta yang menolak tampil karena satu huruf salah di kotak tanggal
        // lebih merepotkan daripada peta yang mengabaikan saringan itu.
        $this->assertSame(1, $this->located('dari=kemarin-sore&sampai=xx'));
    }

    /* ------------------------------------------------------ batas wilayah */

    public function test_the_boundary_is_offered_as_a_url_not_embedded(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(config('complaints.boundary_path'), '{"type":"Feature"}');

        $this->complaint('jalan');

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.map'));

        // Alamatnya, bukan isinya: batas berukuran ratusan kilobyte dan tidak
        // pernah berubah, jadi menempelkannya ke HTML berarti mengirim ulang
        // seluruhnya pada setiap penyaringan.
        $this->assertStringContainsString(
            'batas-wilayah.geojson',
            (string) $response->viewData('boundary'),
        );

        $response->assertDontSee('"type":"Feature"', false);
    }

    public function test_the_map_still_works_without_a_boundary_file(): void
    {
        Storage::fake('public');

        $this->complaint('jalan');

        // Sebuah layar yang menolak tampil karena satu berkas pelengkap belum
        // diunduh tidak menolong siapa pun.
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.map'))
            ->assertOk()
            ->assertViewHas('boundary', null);
    }

    /* --------------------------------------------------------- popup */

    public function test_a_pin_carries_the_photo_and_the_gist(): void
    {
        $complaint = $this->complaint('jalan');
        $complaint->update(['description' => 'Jalan berlubang besar di depan pasar lama.']);

        $attachment = $complaint->attachments()->create([
            'kind' => 'report', 'path' => 'bot/foto-uji.jpg', 'mime' => 'image/jpeg',
        ]);

        $point = collect($this->points())->firstOrFail();

        // Lewat rute bergerbang izin, bukan URL disk publik: ini foto rumah
        // dan pekarangan orang.
        $this->assertSame(route('admin.complaints.attachment', $attachment), $point['photo']);
        $this->assertSame(1, $point['photos']);
        $this->assertStringContainsString('berlubang besar', $point['description']);
    }

    public function test_a_pin_without_a_photo_says_so_rather_than_pointing_at_nothing(): void
    {
        $this->complaint('jalan');

        $this->assertNull(collect($this->points())->firstOrFail()['photo']);
    }

    public function test_the_popup_counts_the_photos_it_is_not_showing(): void
    {
        $complaint = $this->complaint('jalan');

        foreach (['satu', 'dua', 'tiga'] as $name) {
            $complaint->attachments()->create([
                'kind' => 'report', 'path' => "bot/{$name}.jpg", 'mime' => 'image/jpeg',
            ]);
        }

        // Hanya foto pertama yang ditampilkan; jumlahnya disebut supaya orang
        // tahu ada yang belum dilihatnya.
        $this->assertSame(3, collect($this->points())->firstOrFail()['photos']);
    }

    public function test_follow_up_proof_is_not_mistaken_for_the_reporters_photo(): void
    {
        $complaint = $this->complaint('jalan');

        $complaint->attachments()->create([
            'kind' => 'resolution', 'path' => 'bot/bukti-selesai.jpg', 'mime' => 'image/jpeg',
        ]);

        // Foto tindak lanjut petugas bukan foto laporan warga; menampilkannya
        // di peta membuat jalan yang sudah ditambal tampak masih berlubang.
        $point = collect($this->points())->firstOrFail();

        $this->assertNull($point['photo']);
        $this->assertSame(0, $point['photos']);
    }

    /* ------------------------------------------------------- warna pin */

    public function test_each_kind_gets_its_own_colour_in_a_fixed_order(): void
    {
        $ordered = ComplaintCategory::orderBy('sort_order')->orderBy('id')->get();

        $this->assertSame(ComplaintCategory::PIN_COLORS[0], $ordered[0]->pinColor());
        $this->assertSame(ComplaintCategory::PIN_COLORS[1], $ordered[1]->pinColor());

        // Warna yang dipakai harus berbeda satu sama lain sejauh paletnya ada.
        $used = $ordered->take(count(ComplaintCategory::PIN_COLORS))->map->pinColor();
        $this->assertSame($used->count(), $used->unique()->count());
    }

    public function test_a_colour_chosen_in_the_panel_wins(): void
    {
        $category = ComplaintCategory::where('slug', 'jalan')->firstOrFail();
        $category->update(['color' => '#123456']);

        $this->assertSame('#123456', $category->fresh()->pinColor());
    }

    public function test_deactivating_a_kind_never_repaints_the_others(): void
    {
        $before = ComplaintCategory::where('slug', 'bangunan')->firstOrFail()->pinColor();

        ComplaintCategory::where('slug', 'irigasi')->update(['is_active' => false]);

        // Warna mengikuti jenisnya, bukan peringkatnya di antara yang aktif.
        // Kalau tidak, menyembunyikan satu jenis membuat "yang biru" berarti
        // hal lain pada peta yang sedang dibaca orang.
        $this->assertSame($before, ComplaintCategory::where('slug', 'bangunan')->firstOrFail()->pinColor());
    }

    public function test_a_new_kind_takes_the_next_free_colour(): void
    {
        $created = ComplaintCategory::create([
            'name' => 'Penerangan Jalan', 'slug' => 'penerangan-jalan',
            'sort_order' => 900, 'is_active' => true,
        ]);

        $taken = ComplaintCategory::where('id', '!=', $created->getKey())->get()->map->pinColor();

        $this->assertNotContains($created->pinColor(), $taken->all());
    }

    public function test_kinds_past_the_palette_fall_back_rather_than_cycling(): void
    {
        // Mengulang palet dari awal membuat dua jenis berwarna sama persis —
        // lebih menyesatkan daripada satu warna netral yang jujur mengaku
        // tidak membawa arti.
        for ($i = 0; $i < count(ComplaintCategory::PIN_COLORS); $i++) {
            ComplaintCategory::create([
                'name' => 'Jenis Tambahan '.$i, 'slug' => 'tambahan-'.$i,
                'sort_order' => 1000 + $i, 'is_active' => true,
            ]);
        }

        $last = ComplaintCategory::orderByDesc('sort_order')->first();

        $this->assertSame(ComplaintCategory::PIN_FALLBACK, $last->pinColor());
    }

    public function test_the_map_no_longer_offers_a_radius_control(): void
    {
        $this->complaint('jalan');

        // Pengelompokan mengikuti perbesaran peta sekarang; tidak ada angka
        // meter yang perlu ditebak orang.
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.map'))
            ->assertOk()
            ->assertDontSee('Jarak satu lokasi');
    }

    public function test_the_screen_says_which_range_it_is_showing(): void
    {
        $this->complaint('jalan');

        $this->actingAs($this->admin)
            ->get(route('admin.complaints.map', ['dari' => now()->subDays(7)->format('Y-m-d')]))
            ->assertOk()
            ->assertSee('Menampilkan pengaduan');
    }
}
