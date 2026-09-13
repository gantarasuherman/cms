<?php

namespace App\Services\Social\Contracts;

use App\Models\SocialPost;
use App\Services\Social\SyncResult;

interface MetricsProvider
{
    /** Whether this provider has everything it needs to make a request. */
    public function configured(): bool;

    /** What an administrator must supply to make it work, for the status screen. */
    public function requirement(): string;

    /** Reads the current figures for one post. Never throws on a remote fault. */
    public function fetch(SocialPost $post): SyncResult;
}
