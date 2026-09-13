<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single entry point for every upload in the CMS.
 *
 * Stored names are generated, never taken from the client, which is what keeps
 * path traversal and double-extension tricks out of the storage layer.
 */
class MediaService
{
    /** Extensions accepted for the image/video library. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg'];

    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];

    /**
     * Stores a file on a public disk and returns the relative path.
     * Used for images that are meant to be served directly (news covers, slides).
     */
    public function storePublic(UploadedFile $file, string $directory): string
    {
        return $file->storeAs($directory, $this->safeName($file), ['disk' => 'public']);
    }

    /**
     * Stores a file on the private disk. Documents live here so they can only
     * be reached through the controlled download route.
     */
    public function storePrivate(UploadedFile $file, string $directory): string
    {
        return $file->storeAs($directory, $this->safeName($file), ['disk' => 'local']);
    }

    /**
     * Copies a stored file under a fresh generated name.
     *
     * Duplicating a record must duplicate its file too: sharing one path
     * between two rows means deleting either one takes the other's image with
     * it. Returns null when there is nothing to copy.
     */
    public function duplicatePublic(?string $path, string $directory): ?string
    {
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $copy = $directory.'/'.Str::random(40).($extension ? '.'.preg_replace('/[^a-z0-9]/i', '', $extension) : '');

        Storage::disk('public')->copy($path, $copy);

        return $copy;
    }

    /**
     * Downloads a remote image onto the public disk and returns its path.
     *
     * Used when a post's picture comes from the platform rather than from an
     * upload. Three things are refused rather than trusted: a non-image
     * content type, a body larger than the cap, and any URL that is not
     * https — the caller hands us whatever an API returned, and a fetch is a
     * request this server makes on somebody's behalf.
     *
     * Returns null on any failure; a missing picture is a card without an
     * image, not a broken install.
     */
    public function storeRemoteImage(?string $url, string $directory, int $maxBytes = 8_388_608): ?string
    {
        if (blank($url) || ! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }

        try {
            $response = Http::timeout(15)->retry(2, 250, throw: false)->get($url);
        } catch (\Throwable $e) {
            Log::warning('Remote image fetch failed', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $mime = strtolower(explode(';', (string) $response->header('Content-Type'))[0]);
        $extension = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };

        // Not an image, whatever the URL claimed. Storing it would put an
        // arbitrary remote file on a public disk under an image's name.
        if ($extension === null) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        $path = $directory.'/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $body);

        return $path;
    }

    public function delete(?string $path, string $disk = 'public'): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    /** Replaces an existing file, removing the old one only after the new one lands. */
    public function replacePublic(?string $existing, UploadedFile $file, string $directory): string
    {
        $path = $this->storePublic($file, $directory);
        $this->delete($existing);

        return $path;
    }

    public function createLibraryEntry(UploadedFile $file, ?string $altText = null): Media
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $type = $this->typeFor($extension);
        $disk = $type === Media::TYPE_DOCUMENT ? 'local' : 'public';

        $path = $type === Media::TYPE_DOCUMENT
            ? $this->storePrivate($file, 'media/documents')
            : $this->storePublic($file, 'media/'.$type.'s');

        return Media::create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => basename($path),
            'path' => $path,
            'disk' => $disk,
            'type' => $type,
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'alt_text' => $altText,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * The media type to serve a stored file as.
     *
     * The recorded type is trusted only when it says something. Telegram hands
     * photographs over as `application/octet-stream`, and a file served under
     * that type is downloaded rather than shown — which is how a complaint's
     * photograph became a file in somebody's Downloads folder instead of a
     * picture on the screen.
     */
    public static function mimeFor(?string $path, ?string $recorded = null): string
    {
        $recorded = strtolower(trim((string) $recorded));

        if ($recorded !== '' && $recorded !== 'application/octet-stream') {
            return $recorded;
        }

        return match (strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'ogg' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }

    public function typeFor(string $extension): string
    {
        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => Media::TYPE_IMAGE,
            in_array($extension, self::VIDEO_EXTENSIONS, true) => Media::TYPE_VIDEO,
            default => Media::TYPE_DOCUMENT,
        };
    }

    /**
     * A random basename plus the *validated* extension. The original client
     * filename never reaches the filesystem.
     */
    private function safeName(UploadedFile $file): string
    {
        $extension = Str::lower($file->extension() ?: $file->getClientOriginalExtension());

        return Str::random(40).'.'.preg_replace('/[^a-z0-9]/', '', $extension);
    }
}

