<?php

namespace App\Services\Public;

use App\Models\Announcement;
use App\Models\SocialLink;
use App\Services\Cache\PublicCache;
use App\Services\Menu\MenuService;
use App\Services\Settings\SettingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Everything the public layout needs on every page: navigation, site identity,
 * footer, social links and the accessibility configuration.
 *
 * Gathered in one place so a view composer can hand it to the layout once,
 * instead of each controller assembling its own version.
 */
class SiteContext
{
    public function __construct(
        private readonly MenuService $menus,
        private readonly SettingService $settings,
        private readonly PublicCache $cache,
    ) {
    }

    /** @return SupportCollection<int, \App\Models\PublicMenu> */
    public function menu(): SupportCollection
    {
        return $this->menus->publicTree();
    }

    /** @return array<string, mixed> */
    public function general(): array
    {
        return $this->settings->group('general');
    }

    /** @return array<string, mixed> */
    public function footer(): array
    {
        return $this->settings->group('footer');
    }

    /** @return array<string, mixed> */
    public function appearance(): array
    {
        return $this->settings->group('appearance');
    }

    /** @return array<string, mixed> */
    public function seo(): array
    {
        return $this->settings->group('seo');
    }

    /**
     * Accessibility configuration for the public widget.
     *
     * Defaults are permissive: if a key was never written, the feature is on,
     * so a fresh install is accessible rather than accidentally stripped.
     *
     * @return array<string, mixed>
     */
    public function accessibility(): array
    {
        $stored = $this->settings->group('accessibility');

        $defaults = [
            'accessibility_enabled' => true,
            'show_widget' => true,
            'widget_position' => 'bottom-right',
            'text_resize_enabled' => true,
            'color_blind_mode_enabled' => true,
            'text_to_speech_enabled' => true,
            'highlight_links_enabled' => true,
            'keyboard_navigation_enabled' => true,
            'reduce_motion_enabled' => true,
        ];

        return array_merge($defaults, $stored);
    }

    /** @return Collection<int, SocialLink> */
    public function socialLinks(): Collection
    {
        return $this->cache->remember(PublicCache::SOCIAL, 'active', fn () => SocialLink::active()->get());
    }

    /**
     * The notices a visitor may be shown, cached like the rest of the
     * site-wide data rather than queried on every page.
     *
     * @return Collection<int, Announcement>
     */
    public function announcements(): Collection
    {
        return $this->cache->remember(
            PublicCache::ANNOUNCEMENTS,
            'live',
            fn () => Announcement::live()->get(),
        );
    }

    public function siteName(): string
    {
        return (string) ($this->general()['site_name'] ?? config('app.name'));
    }
}

