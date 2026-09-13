<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use App\Models\SocialPostMedia;
use App\Services\Cache\PublicCache;
use App\Services\Media\MediaService;
use App\Services\Social\Contracts\MetricsProvider;
use App\Services\Social\Providers\FacebookProvider;
use App\Services\Social\Providers\InstagramProvider;
use App\Services\Social\Providers\UnsupportedProvider;
use App\Services\Social\Providers\YoutubeProvider;

/**
 * Reads engagement figures back from the platforms and writes them onto the
 * posts.
 *
 * This runs on the server, on a schedule — never in a visitor's browser. That
 * is the whole distinction from an embed script: the platform hears from this
 * site once an hour and learns nothing about the people reading it.
 *
 * A failed sync never clears what is already stored. Numbers that went briefly
 * unreadable are better shown slightly stale than wiped, and a blanked card
 * would look like the post lost its likes.
 */
class SocialSyncService
{
    private const IMAGE_DIRECTORY = 'social-posts';

    public function __construct(
        private readonly PublicCache $cache,
        private readonly MediaService $media,
    ) {
    }

    /** @return array<string, MetricsProvider> */
    public function providers(): array
    {
        return [
            'instagram' => new InstagramProvider(),
            'facebook' => new FacebookProvider(),
            'youtube' => new YoutubeProvider(),
            // No read path exists that is both permitted and stable: X's API
            // puts post lookup behind a paid tier, and TikTok's Display API
            // needs per-user OAuth plus app review. Scraping is neither.
            'x' => new UnsupportedProvider('X (Twitter) tidak menyediakan pembacaan metrik pada tingkat gratis. Angkanya tetap diisi manual.'),
            'tiktok' => new UnsupportedProvider('TikTok memerlukan OAuth per pengguna dan app review. Angkanya tetap diisi manual.'),
        ];
    }

    public function providerFor(string $platform): MetricsProvider
    {
        return $this->providers()[$platform]
            ?? new UnsupportedProvider('Platform "'.$platform.'" belum didukung.');
    }

    /**
     * Syncs one post and records the outcome on it either way, so a failure is
     * visible in the admin rather than silent.
     */
    public function sync(SocialPost $post): SyncResult
    {
        if (! config('social.sync_enabled')) {
            return $this->record($post, SyncResult::unsupported('Sinkronisasi dimatikan (SOCIAL_SYNC_ENABLED=false).'));
        }

        return $this->record($post, $this->providerFor($post->platform)->fetch($post));
    }

    /**
     * @param  iterable<SocialPost>  $posts
     * @return array{ok: int, failed: int, unsupported: int}
     */
    public function syncMany(iterable $posts): array
    {
        $tally = ['ok' => 0, 'failed' => 0, 'unsupported' => 0];

        foreach ($posts as $post) {
            $tally[$this->sync($post)->status]++;
        }

        return $tally;
    }

    /**
     * Brings the platform's pictures onto our own disk.
     *
     * Downloaded rather than hot-linked: a hot-linked image would make every
     * visitor's browser call the platform's CDN, which is exactly the tracking
     * this whole design avoids — and those URLs are signed and expire, so the
     * cards would go blank within days.
     *
     * A carousel keeps one row per slide, in the platform's own order. The
     * cover column still points at a single file, so an admin list, the
     * `live` scope and every pre-carousel row keep working unchanged; it is
     * one of the slides rather than a sixth copy of one.
     *
     * @param  array<int, \App\Services\Social\RemoteMedia>  $slides
     */
    private function pullGallery(SocialPost $post, array $slides, int $coverIndex): void
    {
        if ($slides === []) {
            return;
        }

        // Slides hang off the post, so it needs a key before they can be
        // attached. `record()` saves it again afterwards with the figures.
        if (! $post->exists) {
            $post->save();
        }

        // Keyed by fingerprint so an unchanged slide is recognised across a
        // sync. Meta re-signs its URLs constantly, so the query string says
        // nothing about whether the picture itself changed.
        $existing = $post->exists
            ? $post->media()->get()->keyBy(fn (SocialPostMedia $m) => (string) $m->remote_url)
            : collect();

        $kept = [];
        $order = 0;

        foreach ($slides as $slide) {
            $fingerprint = strtok($slide->url, '?') ?: $slide->url;
            $row = $existing->get($fingerprint);

            if ($row === null || blank($row->path)) {
                $path = $this->media->storeRemoteImage($slide->url, self::IMAGE_DIRECTORY);

                if ($path === null) {
                    // One slide that would not download must not cost the post
                    // the slides that did.
                    continue;
                }

                $row = $row ?: new SocialPostMedia();
                $row->path = $path;
                $row->remote_url = $fingerprint;
                [$row->width, $row->height] = $this->dimensions($path);
            }

            $row->kind = $slide->kind;
            $row->sort_order = $order++;
            $post->media()->save($row);

            $kept[] = $row;
        }

        if ($kept === []) {
            return;
        }

        $keptIds = array_map(fn (SocialPostMedia $m) => $m->getKey(), $kept);
        $keptPaths = array_map(fn (SocialPostMedia $m) => $m->path, $kept);

        // Slides the post no longer has: the file goes with the row, unless
        // another slide happens to point at the same stored file.
        foreach ($post->media()->whereNotIn('id', $keptIds)->get() as $gone) {
            if (! in_array($gone->path, $keptPaths, true)) {
                $this->media->delete($gone->path);
            }

            $gone->delete();
        }

        $cover = $kept[min($coverIndex, count($kept) - 1)];
        $previous = $post->image;

        $post->image = $cover->path;
        $post->remote_image_url = $cover->remote_url;
        $post->setRelation('media', collect($kept));

        // The old standalone cover, from before this post had slides. Dropped
        // only once it is certainly not one of the files still in use.
        if (filled($previous) && ! in_array($previous, $keptPaths, true)) {
            $this->media->delete($previous);
        }
    }

    /**
     * The stored picture's shape, so the card can reserve the right box before
     * the image loads and a carousel does not jump as it advances.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function dimensions(string $path): array
    {
        $absolute = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        $size = @getimagesize($absolute);

        return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
    }

    private function record(SocialPost $post, SyncResult $result): SyncResult
    {
        $post->sync_status = $result->status;
        $post->sync_message = mb_substr($result->message, 0, 500);

        if ($result->successful() && $result->metrics !== null) {
            $metrics = $result->metrics;

            // Each figure is written only when the platform actually returned
            // it. A null here means "not reported", never "zero".
            if ($metrics->likes !== null) {
                $post->likes = $metrics->likes;
            }

            if ($metrics->comments !== null) {
                $post->comments = $metrics->comments;
            }

            if ($metrics->handle !== null) {
                $post->account_handle = ltrim($metrics->handle, '@');
            }

            if ($metrics->remoteId !== null) {
                $post->remote_id = $metrics->remoteId;
            }

            // The caption follows the source too: editing a post on the
            // platform should change what this site shows. A post an editor
            // wants to word differently is the case `sync_enabled` is for.
            if ($metrics->caption !== null) {
                $post->caption = $metrics->caption;
            }

            if ($metrics->postedAt !== null && $post->posted_at === null) {
                // Only when unset: an editor who corrected the date meant it.
                $post->posted_at = $metrics->postedAt;
            }

            if ($metrics->mediaType !== null) {
                $post->media_type = $metrics->mediaType;
            }

            // Null means the token may not read comments — leave whatever was
            // there. An empty array is a real answer: the post has none.
            if ($metrics->topComments !== null) {
                $post->top_comments = $metrics->topComments ?: null;
            }

            $this->pullGallery($post, $metrics->media, $metrics->coverIndex);

            $post->synced_at = now();
        }

        $post->save();

        if ($result->successful()) {
            $this->cache->forget(PublicCache::SOCIAL_POSTS, PublicCache::HOMEPAGE);
        }

        return $result;
    }
}
