<?php

namespace App\Services\Bot\Messages;

/**
 * One message from a person, normalised.
 *
 * WhatsApp and Telegram describe the same event very differently; this is the
 * single shape the engine reasons about, so no node handler ever has to know
 * which platform it is serving.
 */
final readonly class IncomingMessage
{
    public function __construct(
        /** The platform's own id for this message — what makes a replay detectable. */
        public string $externalId,
        /** The platform's id for the sender: a wa_id or a Telegram chat id. */
        public string $from,
        public string $type = 'text',
        public ?string $text = null,
        public ?string $mediaPath = null,
        public ?string $mediaMime = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $senderName = null,
        public ?string $senderUsername = null,
        public ?string $senderAvatarPath = null,
        /** The platform's id for the picture, so an unchanged one is not refetched. */
        public ?string $senderAvatarRef = null,
        public array $raw = [],
    ) {
    }

    public function hasImage(): bool
    {
        return $this->type === 'image' && filled($this->mediaPath);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Trimmed and collapsed, which is what every comparison actually wants. */
    public function body(): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $this->text) ?? '');
    }

    public function isCommand(): bool
    {
        return str_starts_with($this->body(), '/');
    }

    /** The command word without its slash and arguments: "/selesai foo" → "selesai". */
    public function command(): ?string
    {
        if (! $this->isCommand()) {
            return null;
        }

        return strtolower(ltrim(explode(' ', $this->body())[0], '/'));
    }
}
