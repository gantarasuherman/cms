<?php

namespace App\Services\Social\Providers;

use App\Models\SocialPost;
use App\Services\Social\Contracts\MetricsProvider;
use App\Services\Social\SocialMetrics;
use App\Services\Social\SyncResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 — the one platform here that needs no OAuth dance: a
 * server API key reads public video statistics directly.
 */
class YoutubeProvider implements MetricsProvider
{
    public function configured(): bool
    {
        return filled(config('social.youtube.api_key'));
    }

    public function requirement(): string
    {
        return 'YOUTUBE_API_KEY — kunci API server dari Google Cloud dengan YouTube Data API v3 diaktifkan.';
    }

    public function fetch(SocialPost $post): SyncResult
    {
        if (! $this->configured()) {
            return SyncResult::unsupported('YouTube belum dikonfigurasi. '.$this->requirement());
        }

        $id = $post->remote_id ?: $this->videoId($post->permalink);

        if ($id === null) {
            return SyncResult::failed('Tautan tidak mengandung id video yang dikenali.');
        }

        try {
            $response = Http::timeout((int) config('social.timeout'))
                ->retry(2, 250, throw: false)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'statistics,snippet',
                    'id' => $id,
                    'key' => config('social.youtube.api_key'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('YouTube sync failed', ['message' => $e->getMessage()]);

            return SyncResult::failed('Tidak dapat menghubungi YouTube.');
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'HTTP '.$response->status();

            return SyncResult::failed(str_replace((string) config('social.youtube.api_key'), '[key]', $message));
        }

        $item = $response->json('items.0');

        if (! $item) {
            return SyncResult::failed('Video tidak ditemukan atau tidak publik.');
        }

        return SyncResult::ok(new SocialMetrics(
            // A channel may hide its like count; absent is not zero.
            likes: isset($item['statistics']['likeCount']) ? (int) $item['statistics']['likeCount'] : null,
            comments: isset($item['statistics']['commentCount']) ? (int) $item['statistics']['commentCount'] : null,
            remoteId: $id,
            handle: $item['snippet']['channelTitle'] ?? null,
            caption: $item['snippet']['title'] ?? null,
            // Highest still YouTube offers, falling back through the sizes it
            // always provides.
            imageUrl: $item['snippet']['thumbnails']['maxres']['url']
                ?? $item['snippet']['thumbnails']['standard']['url']
                ?? $item['snippet']['thumbnails']['high']['url']
                ?? null,
            postedAt: isset($item['snippet']['publishedAt']) ? new \DateTimeImmutable($item['snippet']['publishedAt']) : null,
        ));
    }

    /** Handles watch?v=, youtu.be/, /shorts/ and /embed/ forms. */
    private function videoId(string $permalink): ?string
    {
        if (preg_match('#[?&]v=([A-Za-z0-9_-]{6,})#', $permalink, $m)) {
            return $m[1];
        }

        if (preg_match('#(?:youtu\.be/|/shorts/|/embed/|/live/)([A-Za-z0-9_-]{6,})#', $permalink, $m)) {
            return $m[1];
        }

        return null;
    }
}
