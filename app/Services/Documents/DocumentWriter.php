<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Documents are stored on the private disk, never under public/. The only way
 * to reach one is the download route, which is what lets visibility and the
 * download counter actually mean something.
 */
class DocumentWriter
{
    private const DIRECTORY = 'documents';

    private const DISK = 'local';

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($data, $file) {
            $document = new Document($this->attributes($data));
            $document->uploaded_by = Auth::id();
            $this->attachFile($document, $file);
            $document->save();

            $this->audit->recordModel('create', 'document', $document);
            $this->cache->forget(PublicCache::DOCUMENTS, PublicCache::HOMEPAGE);

            return $document;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Document $document, array $data, ?UploadedFile $file = null): Document
    {
        return DB::transaction(function () use ($document, $data, $file) {
            $original = $document->getOriginal();
            $previousPath = $document->file_path;

            $document->fill($this->attributes($data));

            if ($file) {
                $this->attachFile($document, $file);
            }

            $document->save();

            // The old file is removed only after the row points at the new one,
            // so a failure here never leaves a record with no downloadable file.
            if ($file && $previousPath !== $document->file_path) {
                $this->media->delete($previousPath, self::DISK);
            }

            $this->audit->recordModel('update', 'document', $document, $original);
            $this->cache->forget(PublicCache::DOCUMENTS, PublicCache::HOMEPAGE);

            return $document;
        });
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document) {
            // Soft delete: the file stays on disk so the record can be restored.
            $document->delete();

            $this->audit->record('delete', 'document', $document->getKey());
            $this->cache->forget(PublicCache::DOCUMENTS, PublicCache::HOMEPAGE);
        });
    }

    private function attachFile(Document $document, UploadedFile $file): void
    {
        $document->file_path = $this->media->storePrivate($file, self::DIRECTORY);
        $document->disk = self::DISK;
        // The name shown to visitors, kept apart from the generated name on disk.
        $document->file_name = $file->getClientOriginalName();
        $document->file_extension = strtolower($file->getClientOriginalExtension());
        $document->mime_type = $file->getClientMimeType();
        $document->file_size = (int) $file->getSize();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)
            ->only(['category_id', 'title', 'slug', 'description', 'published_at', 'is_active'])
            ->all();
    }
}

