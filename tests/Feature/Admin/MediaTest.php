<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_the_library_renders(): void
    {
        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertOk();
    }

    public function test_an_image_goes_to_the_public_disk(): void
    {
        $this->actingAs($this->admin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('foto.jpg'),
            'alt_text' => 'Foto kegiatan',
        ])->assertRedirect();

        $media = Media::first();

        $this->assertSame(Media::TYPE_IMAGE, $media->type);
        $this->assertSame('public', $media->disk);
        $this->assertSame('Foto kegiatan', $media->alt_text);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_a_document_goes_to_the_private_disk(): void
    {
        $this->actingAs($this->admin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->create('laporan.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $media = Media::first();

        $this->assertSame(Media::TYPE_DOCUMENT, $media->type);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->path);
    }

    public function test_the_stored_name_never_comes_from_the_client(): void
    {
        $this->actingAs($this->admin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('../../evil name.png'),
        ])->assertRedirect();

        $media = Media::first();

        $this->assertStringNotContainsString('..', $media->path);
        $this->assertStringNotContainsString(' ', $media->path);
        $this->assertMatchesRegularExpression('#^media/images/[A-Za-z0-9]{40}\.png$#', $media->path);
    }

    public function test_deleting_a_media_entry_removes_the_file(): void
    {
        $this->actingAs($this->admin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('hapus.png'),
        ]);

        $media = Media::first();
        $path = $media->path;

        $this->actingAs($this->admin)
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, Media::count());
    }

    public function test_a_viewer_cannot_upload(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('x.png'),
        ])->assertForbidden();
    }
}
