<?php

namespace Tests\Feature\Bot;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use App\Services\Complaints\ComplaintStatistics;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Angka pada layar pengaduan.
 *
 * Yang diuji terutama cara menghitungnya, bukan tampilannya: setiap angka di
 * sini punya satu cara yang salah tetapi terlihat masuk akal.
 */
class ComplaintStatisticsTest extends TestCase
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

    private function complaint(string $slug, string $status = 'baru', int $daysAgo = 0): Complaint
    {
        $complaint = Complaint::create([
            'ticket' => 'ADU-'.strtoupper(str()->random(8)),
            'complaint_category_id' => ComplaintCategory::where('slug', $slug)->value('id'),
            'channel' => 'whatsapp',
            'description' => 'Contoh laporan.',
            'status' => $status,
        ]);

        if ($daysAgo > 0) {
            // Lewat query, bukan mass assignment: `created_at` bukan kolom
            // yang boleh diisi dari luar, dan memang tidak seharusnya.
            $complaint->newQuery()->whereKey($complaint->getKey())
                ->update(['created_at' => now()->subDays($daysAgo)]);

            $complaint->refresh();
        }

        return $complaint;
    }

    private function stats(?int $days = 30): array
    {
        $from = $days === null ? null : now()->subDays($days)->startOfDay();

        return app(ComplaintStatistics::class)->between($from, now());
    }

    /* --------------------------------------------------------- menghitung */

    public function test_it_counts_each_status_even_when_none_arrived(): void
    {
        $this->complaint('jalan', 'selesai');
        $this->complaint('jalan', 'ditolak');

        $stats = $this->stats();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['byStatus']['selesai']);
        $this->assertSame(1, $stats['byStatus']['ditolak']);
        // Status yang kosong tetap disebut, supaya kartunya tidak hilang.
        $this->assertSame(0, $stats['byStatus']['diproses']);
    }

    public function test_the_busiest_and_quietest_kinds_are_named(): void
    {
        $this->complaint('jalan');
        $this->complaint('jalan');
        $this->complaint('jalan');
        $this->complaint('irigasi');

        $stats = $this->stats();

        $this->assertSame('Pengaduan Jalan', $stats['busiest']['label']);
        $this->assertSame(3, $stats['busiest']['value']);

        // Jenis yang tidak pernah diadukan sekali pun ikut dihitung nol —
        // sepi adalah jawaban yang berguna, dan tanpa ini "paling sedikit"
        // akan menunjuk jenis dengan satu laporan.
        $this->assertSame(0, $stats['quietest']['value']);
    }

    public function test_every_active_kind_appears_in_the_chart(): void
    {
        $this->complaint('jalan');

        $kinds = collect($this->stats()['byCategory'])->pluck('label');

        $this->assertSame(
            ComplaintCategory::where('is_active', true)->count(),
            $kinds->count(),
        );
    }

    /* ------------------------------------------------------------ periode */

    public function test_the_period_leaves_older_complaints_out(): void
    {
        $this->complaint('jalan', 'baru', 2);
        $this->complaint('jalan', 'baru', 45);

        $this->assertSame(1, $this->stats(7)['total']);
        $this->assertSame(2, $this->stats(90)['total']);
        $this->assertSame(2, $this->stats(null)['total'], 'Sejak awal harus memuat semuanya.');
    }

    /* ------------------------------------------------------------ dijawab */

    public function test_a_reply_counts_as_answered(): void
    {
        $complaint = $this->complaint('irigasi');

        $complaint->updates()->create([
            'from_status' => 'baru', 'to_status' => 'baru',
            'note' => 'Sudah kami tinjau.', 'source' => 'admin',
        ]);

        $this->assertSame(1, $this->stats()['answered']);
    }

    public function test_closing_a_complaint_is_not_answering_it(): void
    {
        $complaint = $this->complaint('irigasi', 'selesai');

        // Perubahan status juga menulis baris riwayat. Menghitungnya sebagai
        // jawaban membuat setiap laporan yang ditutup diam-diam tampak sudah
        // dijawab — angka yang menyenangkan dan tidak benar.
        $complaint->updates()->create([
            'from_status' => 'baru', 'to_status' => 'selesai',
            'note' => 'Sudah ditambal.', 'source' => 'whatsapp',
        ]);

        $this->assertSame(0, $this->stats()['answered']);
        $this->assertSame(1, $this->stats()['byStatus']['selesai']);
    }

    public function test_three_replies_on_one_complaint_are_still_one(): void
    {
        $complaint = $this->complaint('irigasi');

        foreach (['Satu', 'Dua', 'Tiga'] as $note) {
            $complaint->updates()->create([
                'from_status' => 'baru', 'to_status' => 'baru',
                'note' => $note, 'source' => 'admin',
            ]);
        }

        // Tiga balasan pada satu laporan tetap satu orang yang mendapat kabar.
        $this->assertSame(1, $this->stats()['answered']);
    }

    /* -------------------------------------------------------------- layar */

    public function test_the_screen_renders_for_every_period(): void
    {
        $this->complaint('jalan', 'ditolak');

        foreach (array_keys(ComplaintStatistics::PERIODS) as $period) {
            $this->actingAs($this->admin)
                ->get(route('admin.complaints.index', ['periode' => $period]))
                ->assertOk()
                ->assertSee('Ditolak');
        }
    }

    public function test_an_unknown_period_falls_back_instead_of_breaking(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.index', ['periode' => 'sebulan-lalu-kira-kira']))
            ->assertOk()
            ->assertSee('30 hari terakhir');
    }

    public function test_an_empty_period_says_so_rather_than_showing_nothing(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.complaints.index', ['periode' => '7']))
            ->assertOk()
            ->assertSee('Belum ada pengaduan pada periode ini');
    }
}
