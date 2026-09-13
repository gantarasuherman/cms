<?php

namespace App\Services\Social;

final readonly class SyncResult
{
    public const OK = 'ok';

    public const FAILED = 'failed';

    public const UNSUPPORTED = 'unsupported';

    public function __construct(
        public string $status,
        public string $message,
        public ?SocialMetrics $metrics = null,
        /**
         * True when the post simply is not on the account we hold a token for.
         *
         * That is the ordinary case for somebody else's post, and the caller
         * answers it by trying a thinner public door rather than by reporting
         * a fault. A dead token or an unreachable host is not this.
         */
        public bool $foreign = false,
    ) {
    }

    public static function ok(SocialMetrics $metrics, string $message = 'Tersinkron.'): self
    {
        return new self(self::OK, $message, $metrics);
    }

    public static function failed(string $message, bool $foreign = false): self
    {
        return new self(self::FAILED, $message, foreign: $foreign);
    }

    public static function unsupported(string $message): self
    {
        return new self(self::UNSUPPORTED, $message);
    }

    public function successful(): bool
    {
        return $this->status === self::OK;
    }
}
