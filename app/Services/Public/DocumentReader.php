<?php

namespace App\Services\Public;

use App\Models\Category;
use App\Models\Document;
use App\Services\Cache\PublicCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DocumentReader
{
    public function __construct(private readonly PublicCache $cache)
    {
    }

    /** @return LengthAwarePaginator<Document> */
    public function paginate(?string $categorySlug = null, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return Document::query()
            ->visible()
            ->with('category:id,name,slug')
            ->when($categorySlug, fn ($query) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $categorySlug)->where('type', Category::TYPE_DOCUMENT),
            ))
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            }))
            ->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findBySlug(string $slug): ?Document
    {
        return Document::query()->visible()->with('category:id,name,slug')->where('slug', $slug)->first();
    }

    /** @return Collection<int, Document> */
    public function latest(int $limit = 6): Collection
    {
        return $this->cache->remember(PublicCache::DOCUMENTS, "latest.$limit", fn () => Document::query()
            ->visible()
            ->with('category:id,name,slug')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return $this->cache->remember(PublicCache::CATEGORIES, 'document', fn () => Category::query()
            ->ofType(Category::TYPE_DOCUMENT)
            ->active()
            ->ordered()
            ->withCount(['documents' => fn ($q) => $q->visible()])
            ->get());
    }
}

