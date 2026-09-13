<?php

namespace App\Services\Bot\Messages;

/**
 * One reply the bot wants sent.
 *
 * The engine produces these; a transport turns them into whatever its platform
 * expects. Keeping them as data rather than sending inline is what makes the
 * whole engine testable without a network, and what lets a send be retried
 * from the outbox when a platform is briefly unreachable.
 */
final readonly class OutgoingMessage
{
    public function __construct(
        public string $body,
        public string $type = 'text',
        public ?string $mediaPath = null,
        /** The node that produced this, recorded on the transcript. */
        public ?string $nodeKey = null,
    ) {
    }

    public static function text(string $body, ?string $nodeKey = null): self
    {
        return new self(body: $body, nodeKey: $nodeKey);
    }

    public static function image(string $mediaPath, string $caption = '', ?string $nodeKey = null): self
    {
        return new self(body: $caption, type: 'image', mediaPath: $mediaPath, nodeKey: $nodeKey);
    }
}
