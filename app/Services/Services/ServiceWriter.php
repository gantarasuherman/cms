<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Write-side orchestration for services: image handling, audit trail and cache
 * invalidation, mirroring NewsService so both modules behave the same way.
 */
class ServiceWriter
{
    private const IMAGE_DIRECTORY = 'services';

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?UploadedFile $image = null): Service
    {
        return DB::transaction(function () use ($data, $image) {
            $service = new Service($this->attributes($data));

            if ($image) {
                $service->image = $this->media->storePublic($image, self::IMAGE_DIRECTORY);
            }

            $service->save();

            $this->audit->recordModel('create', 'service', $service);
            $this->cache->forget(PublicCache::SERVICES, PublicCache::HOMEPAGE);

            return $service;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Service $service, array $data, ?UploadedFile $image = null): Service
    {
        return DB::transaction(function () use ($service, $data, $image) {
            $original = $service->getOriginal();

            $service->fill($this->attributes($data));

            if ($image) {
                $service->image = $this->media->replacePublic($service->image, $image, self::IMAGE_DIRECTORY);
            } elseif (! empty($data['remove_image'])) {
                $this->media->delete($service->image);
                $service->image = null;
            }

            $service->save();

            $this->audit->recordModel('update', 'service', $service, $original);
            $this->cache->forget(PublicCache::SERVICES, PublicCache::HOMEPAGE);

            return $service;
        });
    }

    public function delete(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service->delete();

            $this->audit->record('delete', 'service', $service->getKey());
            $this->cache->forget(PublicCache::SERVICES, PublicCache::HOMEPAGE);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)
            ->only(['category_id', 'name', 'slug', 'description', 'content', 'icon',
                'processing_time', 'status', 'sort_order', 'seo_title', 'seo_description', 'seo_keywords'])
            ->all();
    }
}

