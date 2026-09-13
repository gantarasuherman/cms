<?php

namespace App\Services\Social;

/**
 * What one platform reported back about one post.
 *
 * Every field is nullable and independent: platforms expose different subsets,
 * and a figure the platform did not return must stay missing rather than
 * become a zero the site would then display as fact.
 */
final readonly class SocialMetrics
{
    public function __construct(
        public ?int $likes = null,
        public ?int $comments = null,
        public ?string $remoteId = null,
        public ?string $handle = null,
        public ?string $caption = null,
        /** Where the platform serves the picture. Downloaded, never hot-linked. */
        public ?string $imageUrl = null,
        public ?\DateTimeInterface $postedAt = null,
        /** IMAGE, VIDEO or CAROUSEL_ALBUM, as the platform names it. */
        public ?string $mediaType = null,
        /**
         * Every slide, cover first. Empty means the platform reported a single
         * picture — `imageUrl` alone already covers that.
         *
         * @var array<int, RemoteMedia>
         */
        public array $media = [],
        /**
         * A few comments to preview, [{username, text}, …]. Null when the
         * token cannot read comments, which must not be confused with a post
         * that genuinely has none.
         *
         * @var array<int, array{username: string, text: string}>|null
         */
        public ?array $topComments = null,
        /**
         * Which slide the card opens on, counting from 0.
         *
         * A shared carousel link carries `?img_index=` — the slide the person
         * was actually looking at when they copied it, which is the one worth
         * leading with.
         */
        public int $coverIndex = 0,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->likes === null && $this->comments === null
            && $this->handle === null && $this->imageUrl === null;
    }
}
