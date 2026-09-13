<?php

namespace App\Services\Public;

use App\Models\CarouselSlide;
use App\Models\Document;
use App\Models\HomepageSection;
use App\Models\News;
use App\Models\Service;
use App\Models\SocialPost;
use App\Services\Cache\PublicCache;
use App\Services\Settings\SettingService;
use Illuminate\Support\Collection;

/**
 * Turns the homepage_sections rows an administrator arranged into the data
 * each section needs to render.
 *
 * The order, wording, visibility and item counts are all database-driven; the
 * only thing fixed in code is how each *type* is filled.
 */
class HomepageReader
{
    public function __construct(
        private readonly NewsReader $news,
        private readonly ServiceReader $services,
        private readonly DocumentReader $documents,
        private readonly FaqReader $faqs,
        private readonly PublicCache $cache,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * @return Collection<int, array{section: HomepageSection, items: mixed}>
     */
    public function sections(): Collection
    {
        return HomepageSection::active()
            ->get()
            ->map(fn (HomepageSection $section) => [
                'section' => $section,
                'items' => $this->itemsFor($section),
            ])
            // A data-driven section with nothing to show would render as an
            // empty heading, so it is dropped instead.
            ->reject(fn (array $entry) => $this->isEmpty($entry['section'], $entry['items']))
            ->values();
    }

    /** @return Collection<int, CarouselSlide> */
    public function slides(): Collection
    {
        return $this->cache->remember(PublicCache::CAROUSEL, 'live', fn () => CarouselSlide::live()->get());
    }

    private function itemsFor(HomepageSection $section): mixed
    {
        $limit = (int) $section->setting('limit', $this->defaultLimit($section->type));

        return match ($section->type) {
            'carousel' => $this->slides(),
            'featured_news' => $this->news->featured($limit),
            'latest_news' => $this->news->latest($limit),
            'services' => $this->services->highlighted($limit),
            'documents' => $this->documents->latest($limit),
            'faq' => $this->faqs->highlighted($limit),
            'statistics' => $this->statistics(),
            'social_posts' => $this->socialPosts($limit),
            default => null,
        };
    }

    /** @return Collection<int, SocialPost> */
    private function socialPosts(int $limit): Collection
    {
        // Whether Instagram renders its own posts changes which posts can be
        // shown at all, so it is part of the cache key rather than something
        // a stale entry could contradict.
        $embedded = (bool) ($this->settings->group('appearance')['instagram_embed'] ?? true);

        return $this->cache->remember(
            PublicCache::SOCIAL_POSTS,
            'live.'.$limit.($embedded ? '.embed' : ''),
            // Slides eager-loaded: a carousel per card would otherwise be one
            // query per card on every homepage render.
            fn () => SocialPost::live($embedded)->with('media')->limit($limit)->get(),
        );
    }

    /** @return array<int, array{label: string, value: int, icon: string}> */
    private function statistics(): array
    {
        return $this->cache->remember(PublicCache::HOMEPAGE, 'statistics', fn () => [
            ['label' => 'Berita', 'value' => News::published()->count(), 'icon' => 'newspaper'],
            ['label' => 'Layanan', 'value' => Service::published()->count(), 'icon' => 'briefcase'],
            ['label' => 'Dokumen', 'value' => Document::visible()->count(), 'icon' => 'files'],
        ]);
    }

    private function defaultLimit(string $type): int
    {
        return match ($type) {
            'featured_news' => 3,
            'latest_news', 'services', 'documents', 'faq' => 6,
            'social_posts' => 4,
            default => 6,
        };
    }

    private function isEmpty(HomepageSection $section, mixed $items): bool
    {
        // Editorial sections carry their own copy and are never "empty".
        if (in_array($section->type, ['hero', 'banner', 'custom'], true)) {
            return false;
        }

        return $items instanceof Collection || is_array($items)
            ? count($items) === 0
            : $items === null;
    }
}

