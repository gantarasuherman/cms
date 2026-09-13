<?php

namespace Tests\Feature\Admin;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_a_document_is_stored_on_the_private_disk(): void
    {
        $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Rencana Strategis 2026',
            'file' => UploadedFile::fake()->create('renstra.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('admin.documents.index'));

        $document = Document::firstWhere('slug', 'rencana-strategis-2026');

        $this->assertNotNull($document);
        $this->assertSame('local', $document->disk);
        $this->assertStringStartsWith('documents/', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);

        // The visitor-facing name is kept, but it is not the name on disk.
        $this->assertSame('renstra.pdf', $document->file_name);
        $this->assertStringNotContainsString('renstra', $document->file_path);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Berbahaya',
            'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_the_public_can_download_an_active_document_and_the_counter_increases(): void
    {
        Storage::disk('local')->put('documents/test.pdf', 'isi berkas');

        $document = Document::factory()->create([
            'file_path' => 'documents/test.pdf',
            'file_name' => 'panduan.pdf',
            'is_active' => true,
        ]);

        $response = $this->get(route('public.documents.download', $document->slug));

        $response->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=panduan.pdf');

        $this->assertSame(1, $document->fresh()->download_count);
    }

    public function test_an_inactive_document_cannot_be_downloaded(): void
    {
        Storage::disk('local')->put('documents/hidden.pdf', 'rahasia');

        $document = Document::factory()->create([
            'file_path' => 'documents/hidden.pdf',
            'is_active' => false,
        ]);

        $this->get(route('public.documents.download', $document->slug))->assertNotFound();
        $this->assertSame(0, $document->fresh()->download_count);
    }

    public function test_a_document_scheduled_for_later_cannot_be_downloaded_yet(): void
    {
        Storage::disk('local')->put('documents/future.pdf', 'nanti');

        $document = Document::factory()->create([
            'file_path' => 'documents/future.pdf',
            'published_at' => now()->addWeek(),
        ]);

        $this->get(route('public.documents.download', $document->slug))->assertNotFound();
    }

    public function test_the_download_route_does_not_accept_a_traversal_path(): void
    {
        // The route resolves a slug, never a path, so there is nothing to traverse.
        $this->get('/dokumen/'.urlencode('../../.env').'/unduh')->assertNotFound();
    }

    public function test_replacing_a_file_removes_the_previous_one(): void
    {
        Storage::disk('local')->put('documents/old.pdf', 'lama');

        $document = Document::factory()->create(['file_path' => 'documents/old.pdf']);

        $this->actingAs($this->admin)->put(route('admin.documents.update', $document), [
            'title' => $document->title,
            'slug' => $document->slug,
            'file' => UploadedFile::fake()->create('baru.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $document->refresh();

        Storage::disk('local')->assertMissing('documents/old.pdf');
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_a_viewer_cannot_upload_documents(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->post(route('admin.documents.store'), [
            'title' => 'Tidak boleh',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    }
}
