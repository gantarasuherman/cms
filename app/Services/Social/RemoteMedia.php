<?php

namespace App\Services\Social;

/**
 * One picture a platform reported for a post, before it has been downloaded.
 *
 * A video is carried as its poster frame: the file itself stays on the
 * platform, behind the "lihat di Instagram" link. Mirroring it would mean
 * unbounded storage for something whose signed URL expires within days.
 */
final readonly class RemoteMedia
{
    public function __construct(
        public string $url,
        public string $kind = 'image',
    ) {
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }
}
