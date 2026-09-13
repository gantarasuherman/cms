<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use App\Services\Complaints\LocationClusters;
use Database\Seeders\ComplaintCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintMapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ComplaintCategory $roads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ComplaintCategorySeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->roads = ComplaintCategory::firstOrFail();
    }

    private function complaint(float $latitude, float $longitude, array $attributes = []): Complaint
    {
        return Complaint::create($attributes + [
            'ticket' => 'ADU-'.str()->upper(str()->random(8)),
            'complaint_category_id' => $this->roads->getKey(),
            'channel' => 'telegram',
            'reporter_name' => 'Warga',
            'description' => 'Jalan berlubang.',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => 'baru',
        ]);
    }

    private function clusters(int $radius = LocationClusters::DEFAULT_RADIUS)
    {
        return app(LocationClusters::class)->build(Complaint::with('category')->get(), $radius);
    }

    public function test_reports_about_the_same_spot_become_one_finding(): void
    {
        // Three within about 50 metres of each other, one across town.
        $this->complaint(-6.94210, 107.63550, ['address' => 'Jl. Soekarno Hatta depan SPBU']);
        $this->complaint(-6.94225, 107.63590, ['address' => 'Jl. Soekarno Hatta depan SPBU']);
        $this->complaint(-6.94190, 107.63610, ['address' => 'Jl. Soekarno Hatta km 3']);
        $this->complaint(-6.92150, 107.60700, ['address' => 'Jl. Asia Afrika']);

        $clusters = $this->clusters();

        $this->assertCount(2, $clusters);
        $this->assertSame(3, $clusters[0]->count());

        // The finding in words, which is the point of the screen: not "3
        // reports" but "3 reports of what".
        $this->assertSame('3 '.$this->roads->name, $clusters[0]->summary());
        $this->assertSame('Jl. Soekarno Hatta depan SPBU', $clusters[0]->label());
    }

    public function test_a_stretch_of_road_is_one_finding_rather_than_a_chain_of_pairs(): void
    {
        // Each ~150 m from the next; the ends are ~450 m apart. Single-link
        // clustering is deliberate: a damaged stretch is one job, not three.
        $this->complaint(-6.94200, 107.63500);
        $this->complaint(-6.94200, 107.63636);
        $this->complaint(-6.94200, 107.63772);

        $clusters = $this->clusters(200);

        $this->assertCount(1, $clusters);
        $this->assertSame(3, $clusters[0]->count());
    }

    public function test_a_tighter_radius_splits_them_again(): void
    {
        $this->complaint(-6.94200, 107.63500);
        $this->complaint(-6.94200, 107.63636);

        $this->assertCount(2, $this->clusters(100));
    }

    public function test_a_complaint_without_coordinates_is_left_out_rather_than_placed_at_zero(): void
    {
        $this->complaint(-6.94200, 107.63500);
        Complaint::create([
            'ticket' => 'ADU-TANPALOKASI',
            'complaint_category_id' => $this->roads->getKey(),
            'channel' => 'whatsapp',
            'description' => 'Tanpa titik lokasi.',
            'status' => 'baru',
        ]);

        $clusters = $this->clusters();

        // Null coordinates cast to 0,0 would put the report in the Atlantic
        // and invent a cluster nobody reported.
        $this->assertCount(1, $clusters);
        $this->assertSame(1, $clusters[0]->count());
    }

    public function test_a_place_nobody_named_falls_back_to_its_coordinates(): void
    {
        $this->complaint(-6.94210, 107.63550);

        $this->assertSame('-6.94210, 107.63550', $this->clusters()[0]->label());
    }

    public function test_only_unfinished_reports_count_as_outstanding(): void
    {
        $this->complaint(-6.94210, 107.63550, ['status' => 'selesai']);
        $this->complaint(-6.94215, 107.63555, ['status' => 'baru']);

        $this->assertSame(1, $this->clusters()[0]->open());
    }

    public function test_the_screen_renders_and_hands_the_map_its_points(): void
    {
        $this->complaint(-6.94210, 107.63550, ['address' => 'Jl. Soekarno Hatta']);
        $this->complaint(-6.94215, 107.63555, ['address' => 'Jl. Soekarno Hatta']);

        $this->actingAs($this->admin)
            ->get(route('admin.complaints.map'))
            ->assertOk()
            ->assertSee('Jl. Soekarno Hatta')
            ->assertSee('data-complaint-map', false)
            // The tile template points at this application, not at a provider.
            ->assertSee('admin/peta/petak', false)
            ->assertDontSee('tile.openstreetmap.org');
    }

    public function test_a_viewer_without_complaint_rights_cannot_see_where_people_live(): void
    {
        $this->complaint(-6.94210, 107.63550);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('admin.complaints.map'))->assertForbidden();
    }

    /* ------------------------------------------------------------ tile proxy */

    public function test_tiles_are_fetched_by_this_server_and_cached(): void
    {
        Storage::fake('public');
        Http::fake(['tile.openstreetmap.org/*' => Http::response('PETAK-PNG', 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs($this->admin)->get('/admin/peta/petak/16/52443/34115')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        Storage::disk('public')->assertExists('maps/tiles/16/52443/34115.png');

        // Panned over a second time: served from disk, the provider untouched.
        $this->actingAs($this->admin)->get('/admin/peta/petak/16/52443/34115')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_a_tile_outside_the_world_is_refused_before_it_becomes_a_url(): void
    {
        Http::fake();

        // These three numbers become both a remote URL and a file path.
        foreach ([[16, 99999999, 1], [16, 1, -1], [40, 1, 1]] as [$z, $x, $y]) {
            $this->actingAs($this->admin)->get("/admin/peta/petak/$z/$x/$y")->assertNotFound();
        }

        Http::assertNothingSent();
    }

    public function test_the_tile_route_is_behind_the_same_gate_as_the_complaints(): void
    {
        Http::fake();

        $this->get('/admin/peta/petak/16/52443/34115')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/peta/petak/16/52443/34115')->assertForbidden();

        Http::assertNothingSent();
    }
}
