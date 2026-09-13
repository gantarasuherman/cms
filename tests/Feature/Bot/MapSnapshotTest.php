<?php

namespace Tests\Feature\Bot;

use App\Services\Maps\MapSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MapSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function fakeTiles(): void
    {
        // A real 1×1 PNG is enough: what is being tested is the stitching and
        // the caching, not the picture.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Http::fake(['tile.openstreetmap.org/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    }

    public function test_a_map_is_assembled_on_this_server(): void
    {
        Storage::fake('public');
        $this->fakeTiles();

        $path = app(MapSnapshot::class)->for(-6.2088, 106.8456);

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        // Nine tiles around the point, so the crop always has map under it.
        Http::assertSentCount(9);
    }

    public function test_the_same_place_is_fetched_only_once(): void
    {
        Storage::fake('public');
        $this->fakeTiles();

        $first = app(MapSnapshot::class)->for(-6.2088, 106.8456);
        $second = app(MapSnapshot::class)->for(-6.2088, 106.8456);

        $this->assertSame($first, $second);
        // A complaint opened a hundred times costs one fetch.
        Http::assertSentCount(9);
    }

    public function test_nearby_points_share_one_picture(): void
    {
        Storage::fake('public');
        $this->fakeTiles();

        // Rounded to about ten metres: two complaints on the same corner are
        // the same map.
        $a = app(MapSnapshot::class)->for(-6.20881, 106.84561);
        $b = app(MapSnapshot::class)->for(-6.20884, 106.84563);

        $this->assertSame($a, $b);
        Http::assertSentCount(9);
    }

    public function test_a_refused_tile_provider_yields_nothing_rather_than_a_broken_image(): void
    {
        Storage::fake('public');
        Http::fake(['tile.openstreetmap.org/*' => Http::response('', 429)]);

        // Every caller prints the coordinates instead; a map is a convenience.
        $this->assertNull(app(MapSnapshot::class)->for(-6.2088, 106.8456));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_unreachable_provider_is_caught(): void
    {
        Storage::fake('public');
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->assertNull(app(MapSnapshot::class)->for(-6.2088, 106.8456));
    }

    public function test_the_request_identifies_itself_to_the_tile_provider(): void
    {
        Storage::fake('public');
        $this->fakeTiles();

        app(MapSnapshot::class)->for(-6.2088, 106.8456);

        // OpenStreetMap's tile policy refuses anonymous clients.
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'CMS map snapshot'));
    }

    public function test_the_operators_browser_is_never_sent_to_a_tile_provider(): void
    {
        Storage::fake('public');
        $this->fakeTiles();

        $complaint = \App\Models\Complaint::create([
            'ticket' => \App\Models\Complaint::newTicket(),
            'channel' => 'telegram', 'description' => 'Jalan berlubang.',
            'latitude' => -6.2088, 'longitude' => 106.8456, 'status' => 'baru',
        ]);

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\SettingSeeder::class);
        $this->seed(\Database\Seeders\MenuSeeder::class);

        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('Super Admin');

        $content = $this->actingAs($admin)->get(route('admin.complaints.show', $complaint))->assertOk()->getContent();

        // An embedded map, or a hot-linked image, would hand the provider the
        // coordinates of somebody's complaint on every page view.
        $this->assertStringNotContainsString('tile.openstreetmap.org', $content);
        $this->assertStringNotContainsString('maps.googleapis.com', $content);
        $this->assertStringNotContainsString('<iframe', $content);
        // The picture is served from this site.
        $this->assertStringContainsString('/storage/maps/', $content);
    }
}
