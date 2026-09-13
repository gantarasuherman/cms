<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only route that can hand out a stored document.
 *
 * Files live on the private disk, so there is no URL that bypasses this: the
 * visibility check, the counter and the filename all apply on every download.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(string $slug): StreamedResponse
    {
        // Resolved by slug and constrained to published rows. A draft or
        // deactivated document is a 404 for the public, not a 403, so the
        // existence of unpublished records is not disclosed.
        $document = Document::query()->visible()->where('slug', $slug)->firstOrFail();

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->file_path), 404);

        // Counted without touching updated_at, so a download does not look like
        // an editorial change in the listings.
        $document->incrementQuietly('download_count');

        // Content-Disposition is left to download(): setting it here would
        // overwrite the generated one and strip the filename, so the browser
        // would save the file under the URL segment instead.
        return $disk->download($document->file_path, $document->file_name, [
            // Stops a stored HTML/SVG file from being rendered in the site's
            // own origin if one ever slips past validation.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

