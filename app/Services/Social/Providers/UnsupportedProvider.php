<?php

namespace App\Services\Social\Providers;

use App\Models\SocialPost;
use App\Services\Social\Contracts\MetricsProvider;
use App\Services\Social\SyncResult;

/**
 * Stands in for platforms with no usable read path, and says so plainly.
 *
 * The alternative — scraping a public page — breaks whenever the markup moves,
 * is against those platforms' terms, and would have this server impersonating a
 * browser. Saying "not available" is the honest answer; the figures for these
 * platforms stay whatever an administrator typed.
 */
class UnsupportedProvider implements MetricsProvider
{
    public function __construct(private readonly string $reason)
    {
    }

    public function configured(): bool
    {
        return false;
    }

    public function requirement(): string
    {
        return $this->reason;
    }

    public function fetch(SocialPost $post): SyncResult
    {
        return SyncResult::unsupported($this->reason);
    }
}
