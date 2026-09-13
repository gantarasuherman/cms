<?php

namespace App\Services\News;

use App\Models\News;
use App\Models\Tag;
use App\Services\Audit\AuditLogger;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Write-side orchestration for news: image handling, category/tag syncing,
 * publication timestamps, audit trail and cache invalidation all live here so
 * the controller stays a thin HTTP adapter.
 */
class NewsService
{
    private const IMAGE_DIRECTORY = 'news';

    public function __construct(
        private readonly MediaService $media,
        private readonly AuditLogger $audit,
        private readonly PublicCache $cache,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?UploadedFile $image = null): News
    {
        return DB::transaction(function () use ($data, $image) {
            $news = new News($this->attributes($data));
            $news->author_id = Auth::id();

            if ($image) {
                $news->featured_image = $this->media->storePublic($image, self::IMAGE_DIRECTORY);
            }

            $news->save();
            $this->syncRelations($news, $data);

            $this->audit->recordModel('create', 'news', $news);
            $this->cache->forget(PublicCache::NEWS, PublicCache::HOMEPAGE);

            return $news;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(News $news, array $data, ?UploadedFile $image = null): News
    {
        return DB::transaction(function () use ($news, $data, $image) {
            $original = $news->getOriginal();

            $news->fill($this->attributes($data));

            if ($image) {
                $news->featured_image = $this->media->replacePublic($news->featured_image, $image, self::IMAGE_DIRECTORY);
            } elseif (! empty($data['remove_featured_image'])) {
                $this->media->delete($news->featured_image);
                $news->featured_image = null;
            }

            $news->save();
            $this->syncRelations($news, $data);

            $this->audit->recordModel('update', 'news', $news, $original);
            $this->cache->forget(PublicCache::NEWS, PublicCache::HOMEPAGE);

            return $news;
        });
    }

    public function delete(News $news): void
    {
        DB::transaction(function () use ($news) {
            $news->delete();

            $this->audit->record('delete', 'news', $news->getKey());
            $this->cache->forget(PublicCache::NEWS, PublicCache::HOMEPAGE);
        });
    }

    /** Moves an item between draft / published / archived in one place. */
    public function changeStatus(News $news, string $status): News
    {
        $original = $news->getOriginal();

        $news->status = $status;

        if ($status === News::STATUS_PUBLISHED && $news->published_at === null) {
            $news->published_at = now();
        }

        $news->save();

        $this->audit->recordModel($status, 'news', $news, $original);
        $this->cache->forget(PublicCache::NEWS, PublicCache::HOMEPAGE);

        return $news;
    }

    /**
     * Publication timestamp follows the status: publishing without an explicit
     * date means "now", and it is never invented for a draft.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = collect($data)
            ->only(['title', 'slug', 'excerpt', 'content', 'status', 'published_at',
                'is_featured', 'seo_title', 'seo_description', 'seo_keywords'])
            ->all();

        if (($attributes['status'] ?? null) === News::STATUS_PUBLISHED && blank($attributes['published_at'] ?? null)) {
            $attributes['published_at'] = now();
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function syncRelations(News $news, array $data): void
    {
        $news->categories()->sync($data['categories'] ?? []);

        $tagIds = collect($data['tags'] ?? [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->getKey())
            ->all();

        $news->tags()->sync($tagIds);
    }
}

