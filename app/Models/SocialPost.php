<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A social media post republished on the site.
 *
 * The image is stored on this site's own disk and the card links out to the
 * original post. Nothing is fetched from the network when a visitor loads the
 * page — an official embed script would report every visitor to the platform
 * before they had done anything, which is the same objection that keeps web
 * fonts and raw IP addresses out of this project.
 */
class SocialPost extends Model
{
    /** @var array<string, string> */
    public const PLATFORMS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'x' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
    ];

    protected $fillable = [
        'platform', 'account_handle', 'image', 'remote_image_url', 'alt_text', 'caption', 'likes', 'comments',
        'permalink', 'remote_id', 'sync_enabled', 'synced_at', 'sync_status', 'sync_message',
        'posted_at', 'sort_order', 'is_active', 'media_type', 'is_verified', 'top_comments',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'likes' => 'integer',
            'comments' => 'integer',
            'synced_at' => 'datetime',
            'sync_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'sort_order' => 'integer',
            'top_comments' => 'array',
        ];
    }

    /**
     * Which platform a link belongs to.
     *
     * Matched on the host, not on the string containing the word: a URL like
     * https://contoh.test/?r=instagram.com is not an Instagram post.
     */
    public static function platformFromUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST));
        $host = preg_replace('/^(www|m|mobile)\./', '', $host);

        return match (true) {
            $host === 'instagram.com' => 'instagram',
            $host === 'facebook.com', $host === 'fb.watch', $host === 'fb.com' => 'facebook',
            $host === 'x.com', $host === 'twitter.com' => 'x',
            $host === 'tiktok.com', $host === 'vt.tiktok.com' => 'tiktok',
            $host === 'youtube.com', $host === 'youtu.be' => 'youtube',
            default => null,
        };
    }

    /**
     * @param  bool  $embedded  Instagram renders its own posts on this page.
     *                          Those bring their media with them, so such a
     *                          post is showable before anything has been
     *                          downloaded — which is the only case where a
     *                          picture of our own is not required.
     */
    public function scopeLive(Builder $query, bool $embedded = false): Builder
    {
        // A picture is what the grid is made of, so a post still waiting for
        // one is held back rather than shown as an empty tile.
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $embedded
                ? $q->whereNotNull('image')->orWhere('platform', 'instagram')
                : $q->whereNotNull('image'))
            ->orderBy('sort_order')
            ->orderByDesc('posted_at')
            ->orderByDesc('id');
    }

    public function imageUrl(): ?string
    {
        return filled($this->image) ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * True once there is a picture to show — fetched or uploaded.
     *
     * Reads the stored path, not the disk: a filesystem check here would be a
     * stat() per card on every page render, and a file missing from disk is a
     * broken deployment rather than a state worth designing a layout around.
     */
    public function hasImage(): bool
    {
        return filled($this->image);
    }

    public function platformLabel(): string
    {
        return self::PLATFORMS[$this->platform] ?? Str::title($this->platform);
    }

    /**
     * What a screen reader hears in place of the picture.
     *
     * Falls back to the caption, because a post whose image says nothing is a
     * post with nothing to show — unlike a hero photograph, where the heading
     * beside it already carries the meaning.
     */
    public function altText(): string
    {
        return (string) ($this->alt_text ?: Str::limit(strip_tags((string) $this->caption), 120) ?: 'Unggahan '.$this->platformLabel());
    }

    /** Posts the scheduled sync should reach out for. */
    public function scopeSyncable(Builder $query): Builder
    {
        return $query->where('sync_enabled', true)->orderBy('synced_at');
    }

    /**
     * The account the post came from, as it is written on the platform.
     *
     * Falls back to the site name so the card never shows a bare "@".
     */
    public function handle(): string
    {
        $handle = trim((string) $this->account_handle);

        if ($handle === '') {
            return (string) config('app.name');
        }

        return ltrim($handle, '@');
    }

    /** The carousel slides, cover excluded. */
    public function media(): HasMany
    {
        return $this->hasMany(SocialPostMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Every slide to show, in order.
     *
     * The cover is a slide too. Posts recorded before carousels were supported
     * have no `media` rows at all, so the cover standing alone is the normal
     * single-picture case rather than a degraded one.
     *
     * @return Collection<int, SocialPostMedia>
     */
    public function gallery(): Collection
    {
        $slides = $this->relationLoaded('media') ? $this->media : $this->media()->get();

        if ($slides->isNotEmpty()) {
            return $slides;
        }

        if (! $this->hasImage()) {
            return new Collection();
        }

        return new Collection([
            (new SocialPostMedia([
                'kind' => $this->media_type === 'VIDEO' ? 'video' : 'image',
                'path' => $this->image,
                'alt_text' => $this->altText(),
            ]))->setRelation('post', $this),
        ]);
    }

    public function isCarousel(): bool
    {
        return $this->gallery()->count() > 1;
    }

    /**
     * The caption with hashtags and mentions made clickable.
     *
     * Escaped first, linked second: the caption is text an outside platform
     * wrote, so it reaches the page as characters, never as markup. Neither
     * pattern can match anything an escape produced — `&amp;` contains no
     * character a tag or handle is allowed to carry — so the order is safe.
     */
    public function captionHtml(): HtmlString
    {
        $text = e(trim((string) $this->caption));

        $text = preg_replace_callback(
            '/(?<![\w&])#([\p{L}\p{N}_]+)/u',
            fn ($m) => '<a href="https://www.instagram.com/explore/tags/'.rawurlencode($m[1]).'/" target="_blank" rel="noopener noreferrer nofollow" class="ig-link">#'.$m[1].'</a>',
            $text,
        );

        $text = preg_replace_callback(
            '/(?<![\w&])@([A-Za-z0-9._]{1,30})/u',
            fn ($m) => '<a href="https://www.instagram.com/'.rawurlencode(rtrim($m[1], '.')).'/" target="_blank" rel="noopener noreferrer nofollow" class="ig-link">@'.$m[1].'</a>',
            $text,
        );

        return new HtmlString(nl2br($text, false));
    }

    /**
     * A few comments to preview under the caption.
     *
     * @return array<int, array{username: string, text: string}>
     */
    public function previewComments(int $limit = 2): array
    {
        return collect($this->top_comments ?? [])
            ->filter(fn ($c) => filled($c['text'] ?? null))
            ->take($limit)
            ->map(fn ($c) => [
                'username' => ltrim((string) ($c['username'] ?? ''), '@') ?: $this->handle(),
                'text' => (string) $c['text'],
            ])
            ->values()
            ->all();
    }

    /**
     * A count written the way the platforms write it: 1.234 stays 1.234,
     * 12.400 becomes 12,4 rb.
     *
     * Returns null when the number was never recorded — an unknown count must
     * show nothing rather than claim zero.
     */
    public static function formatCount(?int $count): ?string
    {
        if ($count === null) {
            return null;
        }

        return match (true) {
            $count >= 1_000_000 => rtrim(rtrim(number_format($count / 1_000_000, 1, ',', '.'), '0'), ',').' jt',
            $count >= 10_000 => rtrim(rtrim(number_format($count / 1_000, 1, ',', '.'), '0'), ',').' rb',
            default => number_format($count, 0, ',', '.'),
        };
    }

    /** A short line for the card, since a full caption would bury the grid. */
    public function excerpt(int $length = 110): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->caption)), $length);
    }
}
