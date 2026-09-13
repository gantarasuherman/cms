<?php

namespace App\Services\Public;

use App\Models\Category;
use App\Models\Service;
use App\Services\Cache\PublicCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ServiceReader
{
    public function __construct(private readonly PublicCache $cache)
    {
    }

    /** @return LengthAwarePaginator<Service> */
    public function paginate(?string $categorySlug = null, ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        return Service::query()
            ->published()
            ->with('category:id,name,slug')
            ->when($categorySlug, fn ($query) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $categorySlug)->where('type', Category::TYPE_SERVICE),
            ))
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            }))
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * A service with everything a visitor needs to act on it. Requirements,
     * tariffs and steps are loaded together because a service page without
     * them is not an answer.
     */
    public function findBySlug(string $slug): ?Service
    {
        return Service::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'requirements',
                'tariffs' => fn ($q) => $q->where('is_active', true),
                'steps',
            ])
            ->where('slug', $slug)
            ->first();
    }

    /** @return Collection<int, Service> */
    public function highlighted(int $limit = 6): Collection
    {
        return $this->cache->remember(PublicCache::SERVICES, "highlighted.$limit", fn () => Service::query()
            ->published()
            ->with('category:id,name,slug')
            ->ordered()
            ->limit($limit)
            ->get());
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return $this->cache->remember(PublicCache::CATEGORIES, 'service', fn () => Category::query()
            ->ofType(Category::TYPE_SERVICE)
            ->active()
            ->ordered()
            ->withCount(['services' => fn ($q) => $q->published()])
            ->get());
    }
}

