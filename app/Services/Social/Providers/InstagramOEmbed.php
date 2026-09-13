<?php

namespace App\Services\Social\Providers;

use App\Services\Social\RemoteMedia;
use App\Services\Social\SocialMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram's official oEmbed endpoint.
 *
 * The one supported way to read a post that is not on our own account. What it
 * returns is deliberately thin — the account name and a preview frame — and it
 * is worth being blunt about the two things it does **not** return:
 *
 *   - `like_count` and `comments_count`. Meta removed them from oEmbed in
 *     October 2020. Any site showing live counts for a post it does not own is
 *     scraping, which breaks within weeks and is against the platform terms.
 *   - The individual slides of a carousel. Only one preview frame comes back.
 *
 * It is also not anonymous: since the same 2020 change it requires an app
 * access token, `{app-id}|{client-token}`, and the oEmbed Read feature.
 *
 * Responses are cached, because the figures behind them barely move and the
 * endpoint is rate limited per app rather than per post.
 */
class InstagramOEmbed
{
    private const CACHE_TTL = 6 * 3600;

    public function configured(): bool
    {
        return filled(config('social.instagram.oembed_token'));
    }

    public function requirement(): string
    {
        return 'INSTAGRAM_OEMBED_TOKEN berbentuk {app-id}|{client-token} dari aplikasi Meta yang sudah punya fitur oEmbed Read.';
    }

    /**
     * @return SocialMetrics|string  Metrics, or a message explaining the miss.
     */
    public function fetch(string $permalink): SocialMetrics|string
    {
        if (! $this->configured()) {
            return 'oEmbed Instagram belum dikonfigurasi. '.$this->requirement();
        }

        $key = 'social:ig:oembed:'.sha1($this->canonical($permalink));

        $payload = Cache::remember($key, self::CACHE_TTL, function () use ($permalink) {
            $response = $this->request($permalink);

            if ($response === null) {
                return ['error' => 'Tidak dapat menghubungi Instagram.'];
            }

            if ($response->failed()) {
                return ['error' => $this->explain($response)];
            }

            return $response->json() ?: ['error' => 'Instagram menjawab tanpa data.'];
        });

        if (isset($payload['error'])) {
            // Never keep a failure for six hours: a token that was just fixed,
            // or a post that has just been made public, must work on retry.
            Cache::forget($key);

            return (string) $payload['error'];
        }

        $thumbnail = $payload['thumbnail_url'] ?? null;

        return new SocialMetrics(
            handle: $payload['author_name'] ?? null,
            imageUrl: $thumbnail,
            media: $thumbnail ? [new RemoteMedia($thumbnail)] : [],
        );
    }

    private function request(string $permalink): ?\Illuminate\Http\Client\Response
    {
        try {
            return Http::timeout((int) config('social.timeout'))
                ->retry(2, 250, throw: false)
                ->get('https://graph.facebook.com/'.config('social.instagram.version').'/instagram_oembed', [
                    'url' => $this->canonical($permalink),
                    'omitscript' => 'true',
                    'fields' => 'author_name,thumbnail_url,provider_name',
                    'access_token' => config('social.instagram.oembed_token'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram oEmbed failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * oEmbed wants the bare permalink. `?img_index=2` and the tracking
     * parameters a shared link collects make it a different URL to the
     * endpoint, and a different cache key here.
     */
    private function canonical(string $permalink): string
    {
        $parts = parse_url(trim($permalink));

        $host = strtolower($parts['host'] ?? 'www.instagram.com');
        $path = '/'.trim($parts['path'] ?? '', '/').'/';

        return 'https://'.$host.$path;
    }

    private function explain(\Illuminate\Http\Client\Response $response): string
    {
        $code = (int) $response->json('error.code');
        $message = (string) ($response->json('error.message') ?? 'HTTP '.$response->status());

        $hint = match (true) {
            $code === 4, $code === 17, $response->status() === 429 => 'Kuota permintaan ke Instagram habis. Coba lagi nanti.',
            $code === 24 || $response->status() === 404 => 'Unggahan tidak ditemukan — mungkin dihapus, atau akunnya privat.',
            $code === 190 => 'Token oEmbed tidak berlaku lagi.',
            default => null,
        };

        // The token sits in the query string, so it can come back inside an
        // echoed URL.
        $message = str_replace((string) config('social.instagram.oembed_token'), '[token]', $message);

        return $hint ? $hint.' ('.$message.')' : 'oEmbed gagal: '.$message;
    }
}
