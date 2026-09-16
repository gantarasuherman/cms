<?php

namespace Tests\Feature\Bot;

use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Petak peta yang diambil dan disinggahkan server ini.
 *
 * Peramban operator tidak pernah berbicara dengan penyedia peta: sebuah petak
 * diminta ke sini, diambil sekali, lalu disajikan dari disk berapa kali pun
 * digeser. Yang diuji di sini terutama kegagalannya — sebuah peta yang rusak
 * diam-diam tampil sebagai latar abu-abu dengan titik mengambang, dan tidak
 * ada pesan apa pun yang menjelaskan sebabnya.
 */
class MapTileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(MenuSeeder::class);

        Storage::fake('public');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    private function tile(int $z = 16, int $x = 52501, int $y = 33928): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get("/admin/peta/petak/{$z}/{$x}/{$y}");
    }

    /* ------------------------------------------------------------ melayani */

    public function test_a_tile_is_fetched_once_and_then_served_from_disk(): void
    {
        Http::fake(['tile.openstreetmap.org/*' => Http::response('PNGBYTES', 200)]);

        $this->tile()->assertOk()->assertHeader('Content-Type', 'image/png');

        Storage::disk('public')->assertExists('maps/tiles/16/52501/33928.png');

        // Geseran kedua tidak boleh menyentuh OpenStreetMap lagi: inilah yang
        // membuat peta ini tetap berada di dalam batas kebijakan mereka.
        $this->tile()->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_tile_carries_an_etag_so_a_second_visit_downloads_nothing(): void
    {
        Http::fake(['tile.openstreetmap.org/*' => Http::response('PNGBYTES', 200)]);

        $this->tile()->assertOk()->assertHeader('ETag');
    }

    /* ------------------------------------------------------------ gagal */

    public function test_a_tile_that_cannot_be_cached_is_still_served(): void
    {
        Http::fake(['tile.openstreetmap.org/*' => Http::response('PNGBYTES', 200)]);

        // Izin tulis yang keliru pada storage. Petaknya sudah di tangan; yang
        // gagal hanya menyimpannya untuk lain kali, dan membuang petak yang
        // sudah berhasil diambil membuat seluruh peta tampil kosong.
        Storage::shouldReceive('disk')->andReturnUsing(function () {
            $disk = \Mockery::mock();
            $disk->shouldReceive('exists')->andReturn(false);
            $disk->shouldReceive('put')->andThrow(
                new \League\Flysystem\UnableToCreateDirectory('izin ditolak'),
            );

            return $disk;
        });

        $this->tile()->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_an_unreachable_provider_leaves_a_gap_not_a_broken_map(): void
    {
        Http::fake(['tile.openstreetmap.org/*' => Http::response('', 503)]);

        // Satu petak yang tidak terjangkau meninggalkan lubang pada peta,
        // bukan ikon gambar rusak melintang di atasnya.
        $this->tile()
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    /* ------------------------------------------------------------ batas */

    public function test_coordinates_outside_the_world_are_refused(): void
    {
        Http::fake();

        // Ketiga angka ini menjadi URL dan jalur berkas; tidak ada yang lain
        // yang memeriksanya.
        $this->tile(z: 16, x: 999999, y: 1)->assertNotFound();
        $this->tile(z: 30, x: 1, y: 1)->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_somebody_who_may_not_read_complaints_gets_no_tiles(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get('/admin/peta/petak/16/52501/33928')
            ->assertForbidden();
    }
}
