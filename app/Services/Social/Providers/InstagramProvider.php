<?php

namespace App\Services\Social\Providers;

use App\Models\SocialPost;
use App\Services\Social\Contracts\MetricsProvider;
use App\Services\Social\RemoteMedia;
use App\Services\Social\SocialMetrics;
use App\Services\Social\SyncResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram, read through Meta's two official doors.
 *
 * **Graph API** — for posts on the account this site owns. The only door that
 * reports `like_count` and `comments_count`, and the only one that returns the
 * individual slides of a carousel. It addresses media by id, and a permalink
 * carries only the shortcode, so the account's own media list is read once and
 * matched on permalink; the resolved id is kept on the record and every later
 * sync costs one direct request.
 *
 * **oEmbed** — the fallback, for a post belonging to somebody else. It gives
 * the account name and one preview frame and nothing more; in particular Meta
 * removed the counts from it in 2020. Reaching for them anyway would mean
 * scraping the page, which breaks whenever Instagram changes its markup, gets
 * the server's address blocked, and is against the platform terms.
 *
 * Both run on the server, on a schedule. That is the whole difference from an
 * embed script: the platform learns about this site once an hour, never about
 * the people reading it.
 */
class InstagramProvider implements MetricsProvider
{
    // `children` covers carousel albums: a carousel's own media_url is absent,
    // so the pictures have to come from its children.
    private const FIELDS = 'id,permalink,like_count,comments_count,caption,username,media_type,media_url,thumbnail_url,timestamp,children{media_url,thumbnail_url,media_type}';

    public function __construct(private readonly InstagramOEmbed $oembed = new InstagramOEmbed()) {}

    public function configured(): bool
    {
        return (filled(config('social.instagram.user_id')) && filled(config('social.instagram.token')))
            || $this->oembed->configured();
    }

    public function requirement(): string
    {
        return 'INSTAGRAM_USER_ID dan INSTAGRAM_ACCESS_TOKEN — akun Bisnis/Kreator yang tertaut ke Halaman Facebook, token Halaman berumur panjang dengan izin instagram_basic dan pages_read_engagement. Untuk unggahan akun lain: '.$this->oembed->requirement();
    }

    public function fetch(SocialPost $post): SyncResult
    {
        if (! $this->configured()) {
            // Neither door is open. "Unsupported" rather than "failed": there
            // is nothing wrong to retry, something has simply not been set up.
            return SyncResult::unsupported('Instagram belum dikonfigurasi. '.$this->requirement());
        }

        if (! $this->graphConfigured()) {
            // No account of our own to ask. oEmbed is the only door left, and
            // it is a real one — just a thinner one.
            return $this->viaOEmbed($post, 'Graph API Instagram belum dikonfigurasi.');
        }

        $result = $post->remote_id
            ? $this->byId($post->remote_id, $this->imageIndex($post->permalink))
            : $this->byPermalink($post);

        // "Not on this account" is the ordinary case for somebody else's post,
        // not a fault. Every other failure — a dead token, an unreachable
        // host — is reported as itself rather than papered over.
        if ($result->status === 'failed' && $result->foreign) {
            return $this->viaOEmbed($post, $result->message);
        }

        return $result;
    }

    private function graphConfigured(): bool
    {
        return filled(config('social.instagram.user_id')) && filled(config('social.instagram.token'));
    }

    private function viaOEmbed(SocialPost $post, string $why): SyncResult
    {
        $outcome = $this->oembed->fetch($post->permalink);

        if (is_string($outcome)) {
            return SyncResult::failed($why.' '.$outcome);
        }

        return SyncResult::ok($outcome, $why.' Data diambil lewat oEmbed resmi: nama akun dan gambar pratinjau. Jumlah suka dan komentar tidak disediakan oEmbed, isi manual bila perlu.');
    }

    private function byId(string $id, ?int $imageIndex = null): SyncResult
    {
        $response = $this->get($this->base().'/'.$id, ['fields' => self::FIELDS]);

        if ($response === null) {
            return SyncResult::failed('Tidak dapat menghubungi Instagram.');
        }

        if ($response->failed()) {
            // A deleted post, or a token that has lost access to it. Falling
            // back to a scan would hide a revoked token behind a slow success.
            return SyncResult::failed($this->error($response, 'Unggahan tidak dapat dibaca'));
        }

        return SyncResult::ok($this->toMetrics($response->json(), $imageIndex));
    }

    private function byPermalink(SocialPost $post): SyncResult
    {
        $url = $this->base().'/'.config('social.instagram.user_id').'/media';
        $query = ['fields' => self::FIELDS, 'limit' => 100];
        $wanted = $this->normalise($post->permalink);

        // Three pages of 100 covers a year of daily posting; beyond that the
        // post is old enough that its figures have long since settled.
        for ($page = 0; $page < 3; $page++) {
            $response = $this->get($url, $query);

            if ($response === null) {
                return SyncResult::failed('Tidak dapat menghubungi Instagram.');
            }

            if ($response->failed()) {
                return SyncResult::failed($this->error($response, 'Daftar unggahan tidak dapat dibaca'));
            }

            foreach ($response->json('data', []) as $item) {
                if ($this->normalise($item['permalink'] ?? '') === $wanted) {
                    return SyncResult::ok($this->toMetrics($item, $this->imageIndex($post->permalink)));
                }
            }

            $next = $response->json('paging.next');

            if (! $next) {
                break;
            }

            $url = $next;
            $query = [];
        }

        return SyncResult::failed('Unggahan tidak ditemukan pada akun ini.', foreign: true);
    }

    private function toMetrics(array $item, ?int $imageIndex = null): SocialMetrics
    {
        $slides = $this->slidesOf($item);
        $cover = min(max(0, ($imageIndex ?? 1) - 1), max(0, count($slides) - 1));

        return new SocialMetrics(
            likes: isset($item['like_count']) ? (int) $item['like_count'] : null,
            comments: isset($item['comments_count']) ? (int) $item['comments_count'] : null,
            remoteId: $item['id'] ?? null,
            handle: $item['username'] ?? null,
            caption: $item['caption'] ?? null,
            imageUrl: $slides[$cover]->url ?? null,
            postedAt: isset($item['timestamp']) ? new \DateTimeImmutable($item['timestamp']) : null,
            mediaType: $item['media_type'] ?? null,
            media: $slides,
            // A second request, so only when it was asked for and there is
            // something to read.
            topComments: config('social.instagram.read_comments')
                && isset($item['id']) && (int) ($item['comments_count'] ?? 0) > 0
                ? $this->commentsOf((string) $item['id'])
                : null,
            coverIndex: $cover,
        );
    }

    /**
     * Every slide of the post, in the order Instagram lists them.
     *
     * A video contributes its poster frame rather than the file: the still is
     * what the card shows, and the play badge over it links to Instagram where
     * the video actually plays.
     *
     * @return array<int, RemoteMedia>
     */
    private function slidesOf(array $item): array
    {
        $children = $item['children']['data'] ?? null;

        if (is_array($children) && $children !== []) {
            return array_values(array_filter(array_map(
                fn (array $child) => $this->slideOf($child),
                $children,
            )));
        }

        $slide = $this->slideOf($item);

        return $slide ? [$slide] : [];
    }

    private function slideOf(array $item): ?RemoteMedia
    {
        $video = ($item['media_type'] ?? null) === 'VIDEO';

        $url = $video
            ? ($item['thumbnail_url'] ?? $item['media_url'] ?? null)
            : ($item['media_url'] ?? $item['thumbnail_url'] ?? null);

        return $url ? new RemoteMedia($url, $video ? 'video' : 'image') : null;
    }

    /**
     * A couple of comments to preview.
     *
     * Asked for separately and on purpose: reading comments needs
     * `instagram_manage_comments`, and Graph rejects the *whole* request when
     * the token lacks a field it was asked for — folding this into FIELDS
     * would cost every install without that permission its like counts too.
     *
     * @return array<int, array{username: string, text: string}>|null
     */
    private function commentsOf(string $id): ?array
    {
        $response = $this->get($this->base().'/'.$id.'/comments', [
            'fields' => 'username,text,like_count',
            'limit' => 3,
        ]);

        if ($response === null || $response->failed()) {
            // Null, not []: "we may not read comments" is a different fact
            // from "this post has none", and the card must not claim the
            // second when it only knows the first.
            return null;
        }

        return collect($response->json('data', []))
            ->filter(fn ($c) => filled($c['text'] ?? null))
            ->map(fn ($c) => [
                'username' => (string) ($c['username'] ?? ''),
                'text' => mb_substr((string) $c['text'], 0, 300),
            ])
            ->values()
            ->all();
    }

    /** `?img_index=2` on a carousel link names the slide the person was looking at. */
    private function imageIndex(string $permalink): ?int
    {
        parse_str((string) parse_url($permalink, PHP_URL_QUERY), $query);

        return isset($query['img_index']) && ctype_digit((string) $query['img_index'])
            ? (int) $query['img_index']
            : null;
    }

    /** Trailing slashes and query strings differ between copies of the same link. */
    private function normalise(string $permalink): string
    {
        $path = parse_url(strtolower(trim($permalink)), PHP_URL_PATH) ?: '';

        return rtrim($path, '/');
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.config('social.instagram.version');
    }

    private function get(string $url, array $query): ?\Illuminate\Http\Client\Response
    {
        try {
            return Http::timeout((int) config('social.timeout'))
                ->retry(2, 250, throw: false)
                ->get($url, $query + ['access_token' => config('social.instagram.token')]);
        } catch (\Throwable $e) {
            // Never let a remote fault reach the caller as an exception: one
            // unreachable platform must not abort a sync of every other post.
            Log::warning('Instagram sync failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function error(\Illuminate\Http\Client\Response $response, string $prefix): string
    {
        $code = (int) $response->json('error.code');
        $message = $response->json('error.message') ?? 'HTTP '.$response->status();

        $hint = match (true) {
            $code === 4, $code === 17, $response->status() === 429 => 'Kuota permintaan ke Instagram habis, coba lagi nanti',
            $code === 190 => 'Token Instagram tidak berlaku lagi',
            $response->status() === 404 => 'Unggahan sudah dihapus atau tidak dapat diakses',
            default => $prefix,
        };

        // The token is in the query string, so it can appear in an echoed URL.
        return $hint.': '.str_replace((string) config('social.instagram.token'), '[token]', $message);
    }
}
