<?php

namespace App\Services\Public;

use App\Models\Category;
use App\Models\News;
use App\Models\Tag;
use App\Services\Cache\PublicCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-side access to published news, shared by the Blade site and the public
 * API so the two can never disagree about what is visible.
 *
 * Every query starts from published(), which is the single place that decides
 * what the public may see.
 */
class NewsReader
{
    public function __construct(private readonly PublicCache $cache)
    {
    }

    /** @return LengthAwarePaginator<News> */
    public function paginate(?string $categorySlug = null, ?string $search = null, int $perPage = 9): LengthAwarePaginator
    {
        return News::query()
            ->published()
            ->with(['author:id,name', 'categories:id,name,slug'])
            ->when($categorySlug, fn ($query) => $query->whereHas(
                'categories',
                fn ($q) => $q->where('categories.slug', $categorySlug)->where('categories.type', Category::TYPE_NEWS),
            ))
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            }))
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findBySlug(string $slug): ?News
    {
        return News::query()
            ->published()
            ->with(['author:id,name', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->where('slug', $slug)
            ->first();
    }

    /** @return Collection<int, News> */
    public function featured(int $limit = 3): Collection
    {
        return $this->cache->remember(PublicCache::NEWS, "featured.$limit", fn () => News::query()
            ->published()
            ->featured()
            ->with('categories:id,name,slug')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }

    /** @return Collection<int, News> */
    public function latest(int $limit = 6): Collection
    {
        return $this->cache->remember(PublicCache::NEWS, "latest.$limit", fn () => News::query()
            ->published()
            ->with('categories:id,name,slug')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }

    /** Other published items sharing a category, excluding the one being read. */
    public function related(News $news, int $limit = 3): Collection
    {
        $categoryIds = $news->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return new Collection();
        }

        return News::query()
            ->published()
            ->whereKeyNot($news->getKey())
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /** Recorded without touching updated_at, so reading is not an edit. */
    public function recordView(News $news): void
    {
        $news->incrementQuietly('views');
    }

    /** Most-read published items, for the magazine sidebar. @return Collection<int, News> */
    public function popular(int $limit = 5): Collection
    {
        return $this->cache->remember(PublicCache::NEWS, "popular.$limit", fn () => News::query()
            ->published()
            ->orderByDesc('views')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }

    /** Tags actually attached to published items. @return Collection<int, Tag> */
    public function tags(int $limit = 20): Collection
    {
        return $this->cache->remember(PublicCache::NEWS, "tags.$limit", fn () => Tag::query()
            ->whereHas('news', fn ($query) => $query->published())
            ->withCount(['news' => fn ($query) => $query->published()])
            ->orderByDesc('news_count')
            ->limit($limit)
            ->get());
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return $this->cache->remember(PublicCache::CATEGORIES, 'news', fn () => Category::query()
            ->ofType(Category::TYPE_NEWS)
            ->active()
            ->ordered()
            ->withCount(['news' => fn ($q) => $q->published()])
            ->get());
    }
}

