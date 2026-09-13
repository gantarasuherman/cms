<?php

namespace App\Services\Cache;

use App\Services\Menu\MenuService;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Cache;

/**
 * Owns the cache keys behind the public site.
 *
 * Keys are enumerated rather than tagged so the CMS works on the file and
 * database cache drivers too, not only Redis.
 */
class PublicCache
{
    public const NEWS = 'public.news';

    public const SERVICES = 'public.services';

    public const DOCUMENTS = 'public.documents';

    public const FAQS = 'public.faqs';

    public const CAROUSEL = 'public.carousel';

    public const HOMEPAGE = 'public.homepage';

    public const CATEGORIES = 'public.categories';

    public const SOCIAL = 'public.social';

    public const ANNOUNCEMENTS = 'public.announcements';

    public const SOCIAL_POSTS = 'public.social_posts';

    public const TTL = 600;

    /** @var array<int, string> */
    private array $tracked = [];

    public function remember(string $group, string $suffix, \Closure $callback): mixed
    {
        $key = $this->key($group, $suffix);
        $this->track($group, $key);

        return Cache::remember($key, self::TTL, $callback);
    }

    public function key(string $group, string $suffix = ''): string
    {
        return $suffix === '' ? $group : $group.'.'.sha1($suffix);
    }

    /** Drops every cached entry for the given groups. */
    public function forget(string ...$groups): void
    {
        foreach ($groups as $group) {
            foreach (Cache::get($this->indexKey($group), []) as $key) {
                Cache::forget($key);
            }

            Cache::forget($this->indexKey($group));
            Cache::forget($group);
        }
    }

    public function flushAll(): void
    {
        $this->forget(
            self::NEWS, self::SERVICES, self::DOCUMENTS, self::FAQS,
            self::CAROUSEL, self::HOMEPAGE, self::CATEGORIES, self::SOCIAL,
        );

        app(MenuService::class)->forget();
        app(SettingService::class)->forget();
    }

    /** Records a derived key so it can be invalidated with its group. */
    private function track(string $group, string $key): void
    {
        $index = $this->indexKey($group);
        $keys = Cache::get($index, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever($index, $keys);
        }
    }

    private function indexKey(string $group): string
    {
        return $group.'.__keys';
    }
}

