<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaRequest;
use App\Models\Media;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Media::class);

        $type = $request->string('type')->toString();

        return view('admin.media.index', [
            'items' => Media::query()
                ->when(in_array($type, [Media::TYPE_IMAGE, Media::TYPE_VIDEO, Media::TYPE_DOCUMENT], true),
                    fn ($query) => $query->where('type', $type))
                ->latest()
                ->paginate(24)
                ->withQueryString(),
            'type' => $type,
        ]);
    }

    public function store(MediaRequest $request): RedirectResponse
    {
        $this->authorize('create', Media::class);

        $media = $this->media->createLibraryEntry($request->file('file'), $request->input('alt_text'));

        $this->audit->recordModel('create', 'media', $media);

        return back()->with('success', 'Berkas berhasil diunggah.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        // Hard delete: the library has no soft-delete, so the file goes with
        // the row rather than being orphaned on disk.
        $this->media->delete($media->path, $media->disk);
        $media->delete();

        $this->audit->record('delete', 'media', $media->getKey());

        return back()->with('success', 'Berkas berhasil dihapus.');
    }
}

