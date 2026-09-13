<?php

namespace App\Services\Social\Providers;

use App\Models\SocialPost;
use App\Services\Social\Contracts\MetricsProvider;
use App\Services\Social\SocialMetrics;
use App\Services\Social\SyncResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Page posts, through the Graph API.
 *
 * Reactions rather than likes: the figure a Page shows beside a post is the
 * total of every reaction, not only the thumb.
 */
class FacebookProvider implements MetricsProvider
{
    private const FIELDS = 'id,permalink_url,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0),message,full_picture,created_time';

    public function configured(): bool
    {
        return filled(config('social.facebook.page_id')) && filled(config('social.facebook.token'));
    }

    public function requirement(): string
    {
        return 'FACEBOOK_PAGE_ID dan FACEBOOK_PAGE_TOKEN — token Halaman dengan izin pages_read_engagement.';
    }

    public function fetch(SocialPost $post): SyncResult
    {
        if (! $this->configured()) {
            return SyncResult::unsupported('Facebook belum dikonfigurasi. '.$this->requirement());
        }

        if ($post->remote_id) {
            $response = $this->get($this->base().'/'.$post->remote_id, ['fields' => self::FIELDS]);

            if ($response === null) {
                return SyncResult::failed('Tidak dapat menghubungi Facebook.');
            }

            return $response->successful()
                ? SyncResult::ok($this->toMetrics($response->json()))
                : SyncResult::failed($this->error($response));
        }

        return $this->byPermalink($post);
    }

    private function byPermalink(SocialPost $post): SyncResult
    {
        $url = $this->base().'/'.config('social.facebook.page_id').'/posts';
        $query = ['fields' => self::FIELDS, 'limit' => 100];
        $wanted = $this->fingerprint($post->permalink);

        for ($page = 0; $page < 3; $page++) {
            $response = $this->get($url, $query);

            if ($response === null) {
                return SyncResult::failed('Tidak dapat menghubungi Facebook.');
            }

            if ($response->failed()) {
                return SyncResult::failed($this->error($response));
            }

            foreach ($response->json('data', []) as $item) {
                if ($wanted !== '' && $this->fingerprint($item['permalink_url'] ?? '') === $wanted) {
                    return SyncResult::ok($this->toMetrics($item));
                }
            }

            $next = $response->json('paging.next');

            if (! $next) {
                break;
            }

            $url = $next;
            $query = [];
        }

        return SyncResult::failed('Unggahan tidak ditemukan pada Halaman ini.');
    }

    private function toMetrics(array $item): SocialMetrics
    {
        return new SocialMetrics(
            likes: isset($item['reactions']['summary']['total_count']) ? (int) $item['reactions']['summary']['total_count'] : null,
            comments: isset($item['comments']['summary']['total_count']) ? (int) $item['comments']['summary']['total_count'] : null,
            remoteId: $item['id'] ?? null,
            caption: $item['message'] ?? null,
            imageUrl: $item['full_picture'] ?? null,
            postedAt: isset($item['created_time']) ? new \DateTimeImmutable($item['created_time']) : null,
        );
    }

    /**
     * Facebook writes the same post's URL several ways, so the numeric id
     * inside it is the only part worth comparing.
     */
    private function fingerprint(string $permalink): string
    {
        preg_match_all('/\d{6,}/', $permalink, $matches);

        return $matches[0] ? end($matches[0]) : '';
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.config('social.facebook.version');
    }

    private function get(string $url, array $query): ?\Illuminate\Http\Client\Response
    {
        try {
            return Http::timeout((int) config('social.timeout'))
                ->retry(2, 250, throw: false)
                ->get($url, $query + ['access_token' => config('social.facebook.token')]);
        } catch (\Throwable $e) {
            Log::warning('Facebook sync failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function error(\Illuminate\Http\Client\Response $response): string
    {
        $message = $response->json('error.message') ?? 'HTTP '.$response->status();

        return str_replace((string) config('social.facebook.token'), '[token]', $message);
    }
}
