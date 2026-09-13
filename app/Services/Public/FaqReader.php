<?php

namespace App\Services\Public;

use App\Models\Category;
use App\Models\Faq;
use App\Services\Cache\PublicCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class FaqReader
{
    public function __construct(private readonly PublicCache $cache)
    {
    }

    /** @return Collection<int, Faq> */
    public function all(?string $categorySlug = null, ?string $search = null): Collection
    {
        return Faq::query()
            ->active()
            ->with('category:id,name,slug')
            ->when($categorySlug, fn ($query) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $categorySlug)->where('type', Category::TYPE_FAQ),
            ))
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")->orWhere('answer', 'like', "%{$search}%");
            }))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Grouped for the accordion. Entries with no category fall under a plain
     * heading rather than disappearing.
     *
     * @return SupportCollection<string, Collection<int, Faq>>
     */
    public function grouped(?string $search = null): SupportCollection
    {
        return $this->all(null, $search)->groupBy(fn (Faq $faq) => $faq->category?->name ?? 'Umum');
    }

    /** @return Collection<int, Faq> */
    public function highlighted(int $limit = 6): Collection
    {
        return $this->cache->remember(PublicCache::FAQS, "highlighted.$limit", fn () => Faq::query()
            ->active()
            ->orderBy('sort_order')
            ->limit($limit)
            ->get());
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return $this->cache->remember(PublicCache::CATEGORIES, 'faq', fn () => Category::query()
            ->ofType(Category::TYPE_FAQ)
            ->active()
            ->ordered()
            ->withCount(['faqs' => fn ($q) => $q->active()])
            ->get());
    }
}

